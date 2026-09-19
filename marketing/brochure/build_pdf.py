#!/usr/bin/env python3
"""A4 공법 소개서 PDF 생성.

brochure.html 의 {{IMG}} 를 레포 루트 경로로, {{QR}} 를 (qrcode 모듈이 있으면) QR 이미지로 치환한 뒤
headless Chrome 으로 A4 한 장 PDF 를 만든다.

    python3 marketing/brochure/build_pdf.py
    → marketing/brochure/태양천_그루빙_공법소개서.pdf
"""
import base64
import io
import subprocess
import sys
import tempfile
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
OUT = HERE / '태양천_그루빙_공법소개서.pdf'


def qr_tag(url: str) -> str:
    try:
        import qrcode  # 선택 의존성. 없으면 QR 없이 만든다
    except ImportError:
        return ''
    buf = io.BytesIO()
    qrcode.make(url, border=1).save(buf, format='PNG')
    return '<img src="data:image/png;base64,' + base64.b64encode(buf.getvalue()).decode() + '" alt="">'


def main() -> int:
    html = (HERE / 'brochure.html').read_text(encoding='utf-8')
    html = html.replace('{{IMG}}', ROOT.as_uri()).replace('{{QR}}', qr_tag('https://taeyang1000.com/cases.html'))
    with tempfile.TemporaryDirectory() as tmp:
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
