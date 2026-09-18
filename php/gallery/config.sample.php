<?php
// php/gallery/config.php 로 복사해서 값을 채운다. config.php 는 커밋하지 않는다 (.gitignore).
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'qudgk02',   // 카페24는 DB 이름 = 계정 ID
        'user' => 'qudgk02',
        'pass' => '',          // 카페24 나의 서비스 관리 > 호스팅 관리 > DB 관리 에서 확인/변경
    ],
    // 로그인 쿠키/CSRF 서명용. `openssl rand -hex 32` 로 생성.
    'secret' => 'CHANGE_ME',
    // 업로드 저장 위치 (웹 루트 기준). 하위에 YYYY/MM 폴더가 자동 생성됨.
    'upload_dir' => 'images/uploads',
    // 갤러리 구분. key 는 DB에 저장되는 값, class 는 project.html 필터 버튼의 data-filter 와 맞춘다.
    'categories' => [
        'equipment' => ['class' => 'cat1', 'label' => '보유장비',   'span' => '그루빙 장비'],
        'field'     => ['class' => 'cat2', 'label' => '그루빙 시공', 'span' => '그루빙 시공'],
    ],
    // 이미지 크기
    'large_max'  => 1600, // 원본(팝업용) 긴 변 최대 px
    'thumb_size' => 600,  // 썸네일 정사각 px (기존 썸네일과 동일)
];
