@echo off
echo �K�v�ȃo�C�i���t�@�C�����_�E�����[�h���܂�
powershell -ExecutionPolicy Unrestricted "%~dp0\prepare-bin.ps1"
echo �o�C�i���t�@�C���̃_�E�����[�h���������܂���
echo �C���X�g�[�����N�����܂�
pushd "%~dp0\bin\Apache\bin\"
"%~dp0\bin\PHP\php.exe" -c "%~dp0\bin\PHP\php.default.ini" "%~dp0\install.php"
popd