# pkg フォルダを材料にして bin フォルダを構成する

# 問題があれば実行を停止
$ErrorActionPreference = "Stop"

# 以下、利用したいツールのパッケージをあらかじめ pkg フォルダに置いておくと適当に展開される

# https://www.apachehaus.com/downloads/ より。必須
$apacheBin = "httpd-2.4.46-o111k-x64-vc15.zip"
$apacheBinSHA256 = "0415c519973bdbab0b3d5911e6333a950829d0d4a4c5ccc96d9b6e238e716e48"

# https://windows.php.net/downloads/releases/ より。必須
$phpBin = "php-7.4.16-Win32-vc15-x64.zip"
$phpBinSHA256 = "a1ba91f0fb941485e8458a1bd81e1891aef44f9722ecea1ed3c972a322a16725"

# https://github.com/tsukumijima/rplsinfo/releases/ より。必須でない
$rplsinfoURL = "https://github.com/tsukumijima/rplsinfo/releases/download/v1.5.1/rplsinfo151.zip"
$rplsinfoBin = "rplsinfo151.zip"
$rplsinfoBinSHA256 = "cf545e884b9565c51d97d46d8c1a9f371f91181c46168720924d0502e8a61b43"

# https://github.com/tsukumijima/DTV-Built/ より。必須でない
$ffmpegURL = "https://github.com/tsukumijima/DTV-Built/raw/master/FFmpeg-4.1.6-64bit-Shared.7z"
$ffmpegBin = "FFmpeg-4.1.6-64bit-Shared.7z"
$ffmpegBinSHA256 = "c998b8112e5ac6ffa0e07fe9c261ec8afe647b85587b8a4cb2054fd63ce0febb"

# https://drive.google.com/drive/folders/0BzA4dIFteM2dS1ZUT1FjTnF3Q0E (rigaya氏のGoogleDrive) より。必須でない
$nvcencURL = "https://github.com/rigaya/NVEnc/releases/download/5.29/NVEncC_5.29_x64.7z"
$nvencBin = "NVEnc_5.29_7zip.7z"
$nvencBinSHA256 = "5d610d9ad0165969219bf905c778c7e39fde23153d1efa6692b601f593e9868a"

# https://drive.google.com/drive/folders/0BzA4dIFteM2dVGZ5dE5lSE5mYVk (rigaya氏のGoogleDrive) より。必須でない
$qsvencURL = "https://github.com/rigaya/QSVEnc/releases/download/4.13/QSVEncC_4.13_x64.7z"
$qsvencBin = "QSVEnc_4.13_7zip.7z"
$qsvencBinSHA256 = "5e4364fbb96ded409a44d9f8d49612f389d1ae563aa6fc4f34ca2952338038ef"

# https://drive.google.com/drive/folders/0BzA4dIFteM2dazcxNnYwRXpvNFU (rigaya氏のGoogleDrive) より。必須でない
$vceencURL = "https://github.com/rigaya/VCEEnc/releases/download/6.09/VCEEncC_6.09_x64.7z"
$vceencBin = "VCEEnc_6.09_7zip.7z"
$vceencBinSHA256 = "f3d123969280e865ae1c15cf56b784a7eaa3e51660e9d43842eefab029ee6f3b"

# 7z.exe のあるフォルダを PATH に追加
$Env:Path += ";C:\Program Files\7-Zip;C:\Program Files (x86)\7-Zip"

pushd -LiteralPath $PSScriptRoot\bin

$exportPhpModules = $false

if (Test-Path Apache) {
    "bin/Apache already exists. skipped."
} else {
    "Preparing bin/Apache..."
    
    if ($apacheBinSHA256 -and ((Get-FileHash ..\pkg\$apacheBin -Algorithm SHA256).Hash -ne $apacheBinSHA256)) {
        throw "Hash error: $apacheBin"
    }
    Expand-Archive ..\pkg\$apacheBin -DestinationPath Apache
    pushd Apache

    mv Apache24\* .
    rm Apache24

    # 不要なフォルダを削除
    rm htdocs -Recurse
    rm icons -Recurse
    rm include -Recurse
    rm lib -Recurse

    # 上書きされる設定を避難
    mv conf\extra\httpd-ssl.conf conf\extra\httpd-ssl.conf.orig
    mv conf\httpd.conf conf\httpd.conf.orig
    mv conf\openssl.cnf conf\openssl.cnf.orig

    # 設定のテンプレをコピー
    cp ..\..\pkg\httpd-ssl.conf conf\extra\
    cp ..\..\pkg\httpd.default.conf conf\
    cp ..\..\pkg\openssl.cnf conf\

    $exportPhpModules = $true
    popd
    "Done."
}

if (Test-Path PHP) {
    "bin/PHP already exists. skipped."
} else {
    "Preparing bin/PHP..."

    if ($phpBinSHA256 -and ((Get-FileHash ..\pkg\$phpBin -Algorithm SHA256).Hash -ne $phpBinSHA256)) {
        throw "Hash error: $phpBin"
    }
    Expand-Archive ..\pkg\$phpBin -DestinationPath PHP
    pushd PHP

    # 不要なモジュールを削除
    rm icudt66.dll -ErrorAction Ignore
    rm icuin66.dll -ErrorAction Ignore
    rm icuio66.dll -ErrorAction Ignore
    rm icuuc66.dll -ErrorAction Ignore
    pushd ext
    ls *.dll | foreach {
        if (($_.Name -ne "php_com_dotnet.dll") -and
            ($_.Name -ne "php_curl.dll") -and
            ($_.Name -ne "php_mbstring.dll") -and
            ($_.Name -ne "php_opcache.dll") -and
            ($_.Name -ne "php_openssl.dll") -and
            ($_.Name -ne "php_pdo_sqlite.dll") -and
            ($_.Name -ne "php_sockets.dll")) {
            rm -LiteralPath $_.Name
        }
    }
    popd

    # 設定の初期値を作成
    $wroteExtension = $false
    Get-Content php.ini-development | foreach {
        $s = $_
        $s = [RegEx]::Replace($s, '^(memory_limit *= *).*$', '${1}512M')
        $s = [RegEx]::Replace($s, '^;(html_errors *= *On)$', '$1')
        $s = [RegEx]::Replace($s, '^(post_max_size *= *).*$', '${1}100M')
        $s = [RegEx]::Replace($s, '^;(extension_dir *= *"ext")$', '$1')
        $s = [RegEx]::Replace($s, '^(upload_max_filesize *= *).*$', '${1}100M')
        $s = [RegEx]::Replace($s, '^;(date\.timezone *=).*$', '$1 Asia/Tokyo')
        $s = [RegEx]::Replace($s, '^;(opcache\.revalidate_freq *= *).*$', '${1}1')
        if (!$wroteExtension -and [RegEx]::IsMatch($s, '^;extension *=')) {
            "extension=com_dotnet"
            "extension=curl"
            "extension=mbstring"
            "zend_extension=opcache"
            "extension=openssl"
            "extension=pdo_sqlite"
            "extension=sockets"
            $wroteExtension = $true
        }
        $s
    } | Out-File php.ini -Encoding ASCII

    $exportPhpModules = $true
    popd
    "Done."
}

if ($exportPhpModules) {
    # PHP から Apache へモジュールを輸出
    "Copying some bin/PHP modules into bin/Apache/bin..."
    cp PHP\libenchant.dll Apache\bin\
    cp PHP\libpq.dll Apache\bin\
    cp PHP\libsasl.dll Apache\bin\
    cp PHP\libsodium.dll Apache\bin\
    cp PHP\libsqlite3.dll Apache\bin\
    cp PHP\libssh2.dll Apache\bin\
    if (!(Test-Path Apache\bin\ext)) { mkdir Apache\bin\ext > $null }
    rm Apache\bin\ext\php_*.dll
    cp PHP\ext\php_*.dll Apache\bin\ext\
    "Done."
}

if (Test-Path rplsinfo) {
    "bin/rplsinfo already exists. skipped."
} else {
    # ない場合はダウンロードする
    if (Test-Path ..\pkg\$rplsinfoBin) {
        "Preparing bin/rplsinfo..."
    } else { 
        "Downloading..."
        curl.exe -Lo "..\pkg\$rplsinfoBin" "$rplsinfoURL"
    }
    #if ($rplsinfoBinSHA256 -and ((Get-FileHash ..\pkg\$rplsinfoBin -Algorithm SHA256).Hash -ne $rplsinfoBinSHA256)) {
        #throw "Hash error: $rplsinfoBin"
    #}
        7z.exe e -orplsinfo ..\pkg\$rplsinfoBin */rplsinfo.txt */x64/rplsinfo.exe
        pushd rplsinfo

        mv rplsinfo.exe rplsinfo-tvrp.exe
        popd
        "Done."
}

if (Test-Path FFmpeg) {
    "bin/FFmpeg already exists. skipped."
} else {
    # ない場合はダウンロードする
    if (Test-Path ..\pkg\$ffmpegBin) {
        "Preparing bin/FFmpeg..."
    } else { 
        "Downloading..."
        curl.exe -Lo "..\pkg\$ffmpegBin" "$ffmpegURL"
    }

    #if ($ffmpegBinSHA256 -and ((Get-FileHash ..\pkg\$ffmpegBin -Algorithm SHA256).Hash -ne $ffmpegBinSHA256)) {
        #throw "Hash error: $ffmpegBin"
    #}
    7z.exe e -oFFmpeg ..\pkg\$ffmpegBin */LICENSE.txt */bin/*.dll */bin/ffmpeg.exe */bin/ffprobe.exe
    pushd FFmpeg

    mv ffmpeg.exe ffmpeg-tvrp.exe
    mv ffprobe.exe ffprobe-tvrp.exe
    popd
    "Done."
}

if (Test-Path NVEncC) {
    "bin/NVEncC already exists. skipped."
} else {
    # ない場合はダウンロードする
    if (Test-Path ..\pkg\$nvencBin) {
        "Preparing bin/NVEncC..."
    } else { 
        "Downloading..."
        curl.exe -Lo "..\pkg\$nvencBin" "$nvcencURL"
    }
    if ($nvcencBinSHA256 -and ((Get-FileHash ..\pkg\$nvcencBin -Algorithm SHA256).Hash -ne $nvcencBinSHA256)) {
        throw "Hash error: $qsvencBin"
    }

    7z.exe e -oNVEncC ..\pkg\$nvencBin
    pushd NVEncC

    mv NVEncC64.exe NVEncC-tvrp.exe
    @('@echo off',
      '%~dp0\NVEncC-tvrp.exe --check-features > nvenc_features.txt') | Out-File NVEncC_feature_test.bat -Encoding ASCII
        @('@echo off',
      '%~dp0\NVEncC-tvrp.exe --check-hw',
      'if errorlevel 0 (echo NVENC is available.) else (echo NVENC is unavailable.)',
      'pause') | Out-File -LiteralPath "NVENCが利用可能か確認 [ダブルクリック].bat" -Encoding ASCII
    popd
    "Done."
}

if (Test-Path QSVEncC) {
    "bin/QSVEncC already exists. skipped."
} else {
    # ない場合はダウンロードする
    if (Test-Path ..\pkg\$qsvencBin) {
        "Preparing bin/QSVEncC..."
    } else { 
        "Downloading..."
        curl.exe -Lo "..\pkg\$qsvencBin" "$qsvencURL"
    }
    #if ($qsvencBinSHA256 -and ((Get-FileHash ..\pkg\$qsvencBin -Algorithm SHA256).Hash -ne $qsvencBinSHA256)) {
        #throw "Hash error: $qsvencBin"
    #}
    7z.exe e -oQSVEncC ..\pkg\$qsvencBin
    pushd QSVEncC

    mv QSVEncC64.exe QSVEncC-tvrp.exe
    @('@echo off',
      '%~dp0\QSVEncC-tvrp.exe --check-features-html') | Out-File -LiteralPath "QSVが利用可能か確認 [ダブルクリック].bat" -Encoding ASCII
    popd
    "Done."
}

if (Test-Path VCEEncC) {
    "bin/VCEEncC already exists. skipped."
} else {
    # ない場合はダウンロードする
    if (Test-Path ..\pkg\$vceencBin) {
        "Preparing bin/VCEEncC..."
    } else { 
        "Downloading..."
        curl.exe -Lo "..\pkg\$vceencBin" "$vceencURL"
    }
    #if ($vceencBinSHA256 -and ((Get-FileHash ..\pkg\$vceencBin -Algorithm SHA256).Hash -ne $vceencBinSHA256)) {
        #throw "Hash error: $vceencBin"
    #}
    7z.exe e -oVCEEncC ..\pkg\$vceencBin
    pushd VCEEncC

    mv VCEEncC64.exe VCEEncC-tvrp.exe
    popd
    "Done."
}
