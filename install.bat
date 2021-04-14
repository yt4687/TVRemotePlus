@echo off
echo 必要なバイナリファイルをダウンロードします
powershell -ExecutionPolicy Unrestricted "%~dp0\prepare-bin.ps1"
echo バイナリファイルのダウンロードが完了しました
echo インストーラを起動します
pushd "%~dp0\bin\Apache\bin\"
"%~dp0\bin\PHP\php.exe" -c "%~dp0\bin\PHP\php.default.ini" "%~dp0\install.php"
popd