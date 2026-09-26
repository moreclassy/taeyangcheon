#!/usr/bin/env python3
"""A4 공법 소개서 PDF 생성.

brochure.html 의 {{IMG}} 를 (인쇄 크기에 맞게 줄인) 사진 경로로, {{QR}} 를 (qrcode 모듈이 있으면) QR 이미지로 치환한 뒤
headless Chrome 으로 A4 한 장 PDF 를 만든다.

사진은 레포 images/ 원본을 긴 변 520px·JPEG 품질 66 로 줄인 임시 사본을 쓴다 (원본 그대로 넣으면 PDF 가 1.5MB).
QR 에는 UTM 을 붙여 GA4 에서 인쇄물 유입을 구분한다 (pip install qrcode 필요, 없으면 QR 없이 생성).

    python3 marketing/brochure/build_pdf.py
    → files/taeyang_grooving_brochure.pdf (사이트의 '소개서 다운로드' 버튼이 이 파일을 가리킴)
"""
import base64
import io
import re
import subprocess
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
OUT = ROOT / 'files' / 'taeyang_grooving_brochure.pdf'
QR_URL = 'https://taeyang1000.com/cases.html?utm_source=brochure&utm_medium=print&utm_campaign=brochure'
IMG_MAX = 520   # px, A4 에서 가장 큰 사진(58mm 폭)이 약 230dpi
IMG_QUALITY = 66


def qr_tag(url: str) -> str:
    try:
        import qrcode  # 선택 의존성. 없으면 QR 없이 만든다
    except ImportError:
        return ''
    buf = io.BytesIO()
    qrcode.make(url, border=1).save(buf, format='PNG')
    return '<img src="data:image/png;base64,' + base64.b64encode(buf.getvalue()).decode() + '" alt="">'


def shrink_images(html: str, tmp: Path) -> str:
    """{{IMG}}/images/... 로 참조한 사진을 인쇄용 크기로 줄여 tmp 에 복사하고 {{IMG}} 를 tmp 로 바꾼다."""
    from PIL import Image, ImageOps
    for rel in sorted(set(re.findall(r'\{\{IMG\}\}/([^"\']+)', html))):
        dst = tmp / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        im = ImageOps.exif_transpose(Image.open(ROOT / rel)).convert('RGB')
        im.thumbnail((IMG_MAX, IMG_MAX), Image.LANCZOS)
        im.save(dst, 'JPEG', quality=IMG_QUALITY, optimize=True)
    return html.replace('{{IMG}}', tmp.as_uri())


def main() -> int:
    OUT.parent.mkdir(exist_ok=True)
    html = (HERE / 'brochure.html').read_text(encoding='utf-8')
    html = html.replace('{{QR}}', qr_tag(QR_URL))
    with tempfile.TemporaryDirectory() as tmp:
        try:
            html = shrink_images(html, Path(tmp))
        except ImportError:  # Pillow 가 없으면 원본 사진 사용
            html = html.replace('{{IMG}}', ROOT.as_uri())
        render = Path(tmp) / 'render.html'
        render.write_text(html, encoding='utf-8')
        subprocess.run([CHROME, '--headless=new', '--disable-gpu', '--no-pdf-header-footer',
                        f'--print-to-pdf={OUT}', render.as_uri()],
                       check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        from pypdf import PdfReader
        n = len(PdfReader(str(OUT)).pages)
        if n != 1:
            print(f'경고: {n}쪽으로 생성됨. 한 장에 맞게 brochure.html 을 줄일 것')
            return 1
    except ImportError:
        pass
    print(f'생성: {OUT.relative_to(ROOT)}')
    return 0


if __name__ == '__main__':
    sys.exit(main())
