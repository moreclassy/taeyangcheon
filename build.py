#!/usr/bin/env python3
"""정적 페이지 빌드 스크립트.

src/pages/*.html (본문 + 메타 블록) 과 src/partials/*.html (head / header / footer) 를 합쳐
레포 루트의 *.html 을 생성한다. 헤더·푸터·네비게이션·CSS/JS 버전은 여기와 partials 한 곳에서만 관리한다.

    python3 build.py           # 전체 페이지 생성
    python3 build.py --check   # 생성 결과가 현재 루트 HTML 과 다르면 종료 코드 1 (커밋 전 확인용)

페이지 소스 형식 (src/pages/about.html):

    <!--page
    title: 회사소개 | 태양천 그루빙
    description: ...
    canonical: /about.html          (사이트 루트 기준 경로. canonical 과 og:url 에 쓰임)
    og_image: images/bg/about.jpg   (og:image. 도메인은 자동으로 붙음)
    preload: images/bg/about.jpg    (첫 화면 배경. 생략 가능)
    nav: about                      (NAV 의 key. 활성 메뉴 표시)
    css: owl-carousel               (플러그인 CSS key, 쉼표 구분. 공통 CSS 는 자동)
    js: owl-carousel, jarallax      (플러그인 JS key. jquery/popper/bootstrap → 여기 목록 → theme-script 순서)
    js_after: gallery               (theme-script 뒤에 올 JS key)
    -->
    <!--head-extra-->  ... <head> 끝부분(CSS 뒤)에 그대로 들어갈 HTML (JSON-LD, 페이지 전용 <style>) ...  <!--/head-extra-->
    ... 본문: <!--header end--> 와 <!--footer start--> 사이에 들어갈 HTML ...
"""
import datetime
import html
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
SRC = ROOT / 'src'
SITE = 'https://taeyang1000.com'

# CSS/JS 를 수정하면 여기 버전을 올린다 (.htaccess 가 css/js 를 7일 캐시함)
VERSION = '20260919'
VERSION_OVERRIDE = {
    'css/default-theme.css': '20260919',
}

# 검색엔진 사이트 소유 확인 메타태그. 값이 비어 있으면 출력하지 않는다.
#  - 네이버 서치어드바이저(searchadvisor.naver.com) → 사이트 등록 → "HTML 태그" 방식의 content 값
#  - Google Search Console → 소유권 확인 → "HTML 태그" 방식의 content 값
SITE_VERIFICATION = {
    'naver-site-verification': '383d2463a8ec2ffb9ac13527a737443255339c44',
    'google-site-verification': 'YI51ssCGxZnsuRKgWVNdtVSzHfZ7aKftsKRJPyr7nvA',
}

# 푸터에 표시할 외부 채널 링크 (아이콘 클래스, 표시 이름, URL). 비어 있으면 출력하지 않는다.
# 예: ('fab fa-youtube', '유튜브', 'https://www.youtube.com/@...'),
#     ('fas fa-pen-nib', '네이버 블로그', 'https://blog.naver.com/...'),
#     ('fab fa-instagram', '인스타그램', 'https://www.instagram.com/...'),
SOCIAL = [
]

CSS = {
    'bootstrap': 'css/bootstrap.min.css',
    'fontawesome': 'css/fontawesome-all.css',
    'animate': 'css/animate.css',
    'themify-icons': 'css/themify-icons.css',
    'magnific-popup': 'css/magnific-popup/magnific-popup.css',
    'owl-carousel': 'css/owl-carousel/owl.carousel.css',
    'slit-slider': 'css/slit-slider/slit-slider.css',
    'base': 'css/base.css',
    'shortcodes': 'css/shortcodes.css',
    'default-theme': 'css/default-theme.css',
    'responsive': 'css/responsive.css',
}
CSS_COMMON_BEFORE = ['bootstrap', 'fontawesome', 'themify-icons']  # themify: 모바일 메뉴(navbar-toggler) 아이콘이 모든 페이지에서 사용
CSS_COMMON_AFTER = ['base', 'shortcodes', 'default-theme', 'responsive']

JS = {
    'jquery': ['js/jquery.3.3.1.min.js'],
    'popper': ['js/popper.min.js'],
    'bootstrap': ['js/bootstrap.min.js'],
    'modernizr': ['js/modernizr.js'],
    'magnific-popup': ['js/magnific-popup/jquery.magnific-popup.min.js'],
    'owl-carousel': ['js/owl-carousel/owl.carousel.min.js'],
    'slit-slider': ['js/slit-slider/jquery.ba-cond.min.js', 'js/slit-slider/jquery.slitslider.js'],
    'jarallax': ['js/jarallax/jarallax.min.js'],
    'isotope': ['js/isotope/isotope.pkgd.min.js'],
    'contact-form': ['js/contact-form/contact-form.js'],
    'theme-script': ['js/theme-script.js'],
    'gallery': ['js/gallery.js'],
    'records': ['js/records.js'],
}
JS_COMMON_BEFORE = ['jquery', 'popper', 'bootstrap']

# 네비게이션 (순서대로). key 는 페이지 메타의 nav 값과 맞춘다
NAV = [
    ('home', 'index.html', 'Home'),
    ('about', 'about.html', '회사소개'),
    ('project', 'project.html', '갤러리'),
    ('cases', 'cases.html', '시공 사례'),
    ('records', 'records.html', '시공 실적'),
    ('info', 'info.html', '그루빙이란?'),
    ('contact', 'contact.html', '연락처'),
]

SITEMAP = ROOT / 'sitemap.xml'

META_RE = re.compile(r'^<!--page\n(.*?)\n-->\n?', re.S)
# 본문 아코디언(FAQ) → FAQPage 구조화 데이터 자동 생성용
FAQ_Q_RE = re.compile(r'<a data-toggle="collapse"[^>]*>(.*?)</a>', re.S)
FAQ_A_RE = re.compile(r'<div class="card-body">(.*?)</div>', re.S)
EXTRA_RE = re.compile(r'<!--head-extra-->\n?(.*?)\n?<!--/head-extra-->\n?', re.S)


def versioned(path: str) -> str:
    return f'{path}?v={VERSION_OVERRIDE.get(path, VERSION)}'


def split_list(value: str):
    return [v.strip() for v in value.split(',') if v.strip()]


def parse_page(text: str):
    m = META_RE.match(text)
    if not m:
        raise SystemExit('메타 블록(<!--page ... -->)이 없습니다')
    meta = {}
    for line in m.group(1).splitlines():
        if not line.strip() or line.lstrip().startswith('#'):
            continue
        key, _, val = line.partition(':')
        meta[key.strip()] = val.strip()
    rest = text[m.end():]
    extra = ''
    em = EXTRA_RE.search(rest)
    if em:
        extra = em.group(1).strip('\n')
        rest = rest[:em.start()] + rest[em.end():]
    body = rest.strip('\n')
    return meta, extra, body


def render_css(keys):
    lines = []
    for k in CSS_COMMON_BEFORE + keys + CSS_COMMON_AFTER:
        lines.append(f'<link href="{versioned(CSS[k])}" rel="stylesheet" type="text/css" />')
    return '\n'.join(lines)


def render_js(keys, after):
    lines = []
    for k in JS_COMMON_BEFORE + keys + ['theme-script'] + after:
        for path in JS[k]:
            lines.append(f'<script src="{versioned(path)}"></script>')
    return '\n'.join(lines)


def render_verification():
    return '\n'.join(
        f'<meta name="{k}" content="{html.escape(v)}" />' for k, v in SITE_VERIFICATION.items() if v
    )


def render_social():
    if not SOCIAL:
        return ''
    items = ''.join(
        f'<li><a href="{html.escape(url)}" target="_blank" rel="noopener" title="{html.escape(label)}">'
        f'<i class="{icon}"></i> {html.escape(label)}</a></li>'
        for icon, label, url in SOCIAL
    )
    return f'<ul class="list-inline footer-social">{items}</ul>'


def strip_tags(s: str) -> str:
    s = re.sub(r'<[^>]+>', '', s)
    s = html.unescape(s)
    s = re.sub(r'\s+', ' ', s).strip()
    return s.rstrip(' →').strip()


def render_faq(body: str) -> str:
    """본문에 아코디언(.accordion)이 있으면 질문·답변을 뽑아 FAQPage JSON-LD 를 만든다.
    HTML 의 문구가 바뀌면 구조화 데이터도 같이 바뀌므로 따로 관리할 필요가 없다."""
    if 'class="accordion' not in body:
        return ''
    qs = [strip_tags(q) for q in FAQ_Q_RE.findall(body)]
    # 답변 끝의 안내 링크(<a>…</a>)는 답변 본문이 아니므로 제외
    ans = [strip_tags(re.sub(r'<a [^>]*>.*?</a>', '', a, flags=re.S)) for a in FAQ_A_RE.findall(body)]
    if not qs or len(qs) != len(ans):
        raise SystemExit(f'FAQ 질문 {len(qs)}개 / 답변 {len(ans)}개 수가 맞지 않습니다')
    data = {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        'mainEntity': [
            {'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}}
            for q, a in zip(qs, ans)
        ],
    }
    return '<script type="application/ld+json">\n' + json.dumps(data, ensure_ascii=False, indent=1) + '\n</script>'


def update_sitemap(changed: list):
    """이번 빌드에서 다시 생성된 페이지의 <lastmod> 를 오늘 날짜로 갱신한다."""
    if not changed or not SITEMAP.exists():
        return
    today = datetime.date.today().isoformat()
    text = SITEMAP.read_text(encoding='utf-8')
    for name in changed:
        loc = SITE + '/' + ('' if name == 'index.html' else name)
        pat = re.compile(r'(<loc>' + re.escape(loc) + r'</loc><lastmod>)[^<]*(</lastmod>)')
        text, n = pat.subn(lambda m: m.group(1) + today + m.group(2), text)
        if n == 0:
            print(f'경고: sitemap.xml 에 {loc} 항목이 없습니다')
    SITEMAP.write_text(text, encoding='utf-8')
    print(f'sitemap.xml lastmod 갱신 ({today}): ' + ', '.join(changed))


def render_nav(active: str):
    items = []
    for key, href, label in NAV:
        cls = 'nav-item active' if key == active else 'nav-item'
        items.append(f'                  <li class="{cls}"> <a class="nav-link" href="{href}"><span class="menu-label">{label}</span></a></li>')
    return '\n'.join(items)


def fill(template: str, values: dict) -> str:
    out = template
    for k, v in values.items():
        out = out.replace('{{' + k + '}}', v)
    leftover = re.findall(r'\{\{[a-z_]+\}\}', out)
    if leftover:
        raise SystemExit(f'치환되지 않은 자리표시자: {leftover}')
    return out


def build_page(src_path: Path) -> str:
    meta, extra, body = parse_page(src_path.read_text(encoding='utf-8'))
    for required in ('title', 'description', 'canonical', 'og_image', 'nav'):
        if required not in meta:
            raise SystemExit(f'{src_path.name}: 메타 "{required}" 누락')
    if meta['nav'] not in {k for k, _, _ in NAV}:
        raise SystemExit(f'{src_path.name}: nav "{meta["nav"]}" 는 NAV 에 없음')

    head = (SRC / 'partials' / 'head.html').read_text(encoding='utf-8')
    header = (SRC / 'partials' / 'header.html').read_text(encoding='utf-8')
    footer = (SRC / 'partials' / 'footer.html').read_text(encoding='utf-8')

    preload = meta.get('preload', '')
    faq = render_faq(body)
    if faq:
        extra = (extra + '\n' + faq) if extra else faq
    values = {
        'title': html.escape(meta['title'], quote=False),
        'title_attr': html.escape(meta['title']),
        'description': html.escape(meta['description']),
        'canonical': SITE + meta['canonical'],
        'og_image': SITE + '/' + meta['og_image'].lstrip('/'),
        'preload': f'<link rel="preload" as="image" href="{preload}" />' if preload else '',
        'head_extra': ('\n' + extra + '\n') if extra else '',
        'verification': render_verification(),
        'social': render_social(),
        'css': render_css(split_list(meta.get('css', ''))),
        'nav': render_nav(meta['nav']),
        'js': render_js(split_list(meta.get('js', '')), split_list(meta.get('js_after', ''))),
        'body': body,
    }
    page = fill(head, values) + fill(header, values) + '\n\n' + body + '\n\n' + fill(footer, values)
    return page


def main(argv):
    check = '--check' in argv
    pages = sorted((SRC / 'pages').glob('*.html'))
    if not pages:
        raise SystemExit('src/pages/*.html 이 없습니다')
    stale = []
    changed = []
    for src_path in pages:
        out_path = ROOT / src_path.name
        rendered = build_page(src_path)
        current = out_path.read_text(encoding='utf-8') if out_path.exists() else None
        if check:
            if current != rendered:
                stale.append(out_path.name)
        elif current != rendered:
            out_path.write_text(rendered, encoding='utf-8')
            changed.append(out_path.name)
            print(f'생성: {out_path.name}')
        else:
            print(f'변경 없음: {out_path.name}')
    if check:
        if stale:
            print('빌드 결과와 다른 파일: ' + ', '.join(stale) + '  → python3 build.py 실행')
            return 1
        print('모든 페이지가 최신 상태입니다')
    else:
        update_sitemap(changed)
    return 0


if __name__ == '__main__':
    sys.exit(main(sys.argv[1:]))
