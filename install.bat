@echo off
pushd "%~dp0\bin\Apache\bin\"
"%~dp0\bin\PHP\php.exe" "%~dp0\install.php"
popd