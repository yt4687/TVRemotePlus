<?php

	// Shift-JIS��
	ini_set('default_charset', 'sjis-win');
	
	// �ׁ[�X�t�H���_
	$base_dir = rtrim(str_replace('\\','/',dirname(__FILE__)), '/');

	// �o�[�W����
	$version = file_get_contents(dirname(__FILE__).'/data/version.txt');

	// Shift-JIS�̃_�������΍�
	function sj_str($text) {
		$str_arr = array('�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\',
						'�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\','�\\', "");
		$text = str_replace("\\\\", "\\", $text);
		for ($i = 0; $str_arr[$i] != ""; $i++) {
			$text = str_replace($str_arr[$i] . "\\", mb_substr($str_arr[$i], 0, 1), $text); // ���\�����Ă����������
			$text = str_replace($str_arr[$i], $str_arr[$i] . "\\", $text); // \����
		}
		return $text;
	}
	
	// ' // ���G�f�B�^�̕\�������������Ȃ錻�ۂւ̑΍�

	// �t�H���_�R�s�[�֐�
	function dir_copy($dir_name, $new_dir){

		if (!is_dir($dir_name)) {
			copy(sj_str($dir_name), sj_str($new_dir));
			return true;
		}
		if (!is_dir($new_dir)) {
			mkdir($new_dir);
		}

		if (is_dir($dir_name)) {
			if ($dh = opendir($dir_name)) {
				while (($file = readdir($dh)) !== false) {
					if ($file == "." || $file == "..") {
						continue;
					}
					if (is_dir($dir_name . "/" . $file)) {
						dir_copy($dir_name . "/" . $file, $new_dir . "/" . $file);
					} else {
						copy(sj_str($dir_name . "/" . $file), sj_str($new_dir . "/" . $file));
					}
				}
				closedir($dh);
			}
		}
		return true;
	}

	// �t�H���_���Ȃ��ꍇ�ɂ̂݃f�B���N�g�����쐬����
	function if_mkdir($mkdir){
		global $serverroot;
		if (!file_exists($serverroot.$mkdir)){
			mkdir($serverroot.$mkdir);
			echo '    �t�H���_ '.$serverroot.$mkdir.' ���쐬���܂����B'."\n";
			echo "\n";
		}
	}

	// �R�s�[
	function if_copy($copy, $flg = false){
		global $base_dir, $serverroot;
		if (!file_exists($serverroot.$copy) or $flg == true){
			dir_copy($base_dir.$copy, $serverroot.$copy);
			echo '    '.$base_dir.$copy.' ��'."\n";
			echo '    '.$serverroot.$copy.' �ɃR�s�[���܂����B'."\n";
			echo "\n";
		}
	}

	// �o��
	echo "\n";
	echo '  -------------------------------------------------------------------'."\n";
	echo '                    TVRemotePlus '.$version.' �C���X�g�[���['."\n";
	echo '  -------------------------------------------------------------------'."\n";
	echo "\n";
	echo '    TVRemotePlus �̃Z�b�g�A�b�v���s���C���X�g�[���[�ł��B'."\n";
	echo '    �r���ŃL�����Z������ꍇ�͂��̂܂܃E�C���h�E����Ă��������B'."\n";
	echo "\n";
	echo '  -------------------------------------------------------------------'."\n";

	echo "\n";
	echo '    1. TVRemotePlus ���C���X�g�[������t�H���_���w�肵�܂��B'."\n";
	echo "\n";
	echo '      �t�H���_���h���b�O&�h���b�v���邩�A�t�H���_�p�X����͂��Ă��������B'."\n";
	echo '      �Ȃ��AUsers�EProgram Files �ȉ��ƁA���{��(�S�p)���܂܂��p�X�A'."\n";
	echo '      ���p�X�y�[�X���܂ރp�X�͐���ɓ��삵�Ȃ��Ȃ錴���ƂȂ邽�߁A�����Ă��������B'."\n";
	echo "\n";
	echo '      �C���X�g�[������t�H���_�F';
	// TVRemotePlus���C���X�g�[������t�H���_
	$serverroot = trim(fgets(STDIN));
	$serverroot = str_replace('"', '', $serverroot);
	echo "\n";
	// �󂾂�����
	if (empty($serverroot)){
		while(empty($serverroot)){
			echo '     ���͗�����ł��B������x���͂��Ă��������B'."\n";
			echo "\n";
			echo '     �C���X�g�[������t�H���_�F';
			$serverroot = trim(fgets(STDIN));
			$serverroot = str_replace('"', '', $serverroot);
			echo "\n";
		}
	}
	// �u��
	$serverroot = str_replace('\\', '/', $serverroot);
	$serverroot = rtrim($serverroot, '/');

	// �t�H���_�����݂���ꍇ�A�b�v�f�[�g
	if (file_exists($serverroot) and file_exists($serverroot.'/config.php')){
		echo '      ���Ɏw�肳�ꂽ�t�H���_�ɃC���X�g�[������Ă���Ɣ��肵�܂����B'."\n";
		echo '      �A�b�v�f�[�g���[�h�ŃC���X�g�[�����܂��B'."\n";
		echo '      ���̂܂܃A�b�v�f�[�g���[�h�ŃC���X�g�[������ɂ� 1 ���A'."\n";
		echo '      �S�ĐV�K�C���X�g�[������ꍇ�� 2 ����͂��Ă��������B'."\n";
		echo "\n";
		echo '      Enter �L�[�Ŏ��ɐi�ޏꍇ�A�����ŃA�b�v�f�[�g���[�h��I�����܂��B'."\n";
		echo "\n";
		echo '      �C���X�g�[�����[�h�F';
		$update_flg = trim(fgets(STDIN));
		// ����
		if ($update_flg == 2) $update = false;
		else $update = true;
		echo "\n";
	} else {	
		$update = false;
	}


	// �V�K�C���X�g�[���̏ꍇ��IP�ƃ|�[�g��u��
	if ($update === false){
		echo '    2. TVRemotePlus ���C���X�g�[������ PC �́A���[�J�� IP �A�h���X����͂��Ă��������B'."\n";
		echo "\n";
		echo '      ���[�J�� IP �A�h���X�́A�ʏ� 192.168.x.xx �̂悤�Ȍ`���̉Ƃ̒��p�� IP �A�h���X�ł��B'."\n";
		echo '      �C���X�g�[���[�Ō��m�������[�J�� IP �A�h���X�� '.getHostByName(getHostName()).' �ł��B'."\n";
		echo '      ���肪�Ԉ���Ă���ꍇ������܂� (VPN �����g���Ă��ĕ����̉��z�f�o�C�X������ꍇ�Ȃ�)'."\n";
		echo '      ���̏ꍇ�A���C���ŗ��p���Ă��郍�[�J�� IP �A�h���X�� ipconfig �Œ��ׁA���͂��Ă��������B'."\n";
		echo "\n";
		echo '      �悭�킩��Ȃ��ꍇ�́AEnter �L�[�������A���ɐi��ł��������B'."\n";
		echo "\n";
		echo '      ���[�J�� IP �A�h���X�F';
		// TVRemotePlus���ғ�������PC(�T�[�o�[)�̃��[�J��LAN��IP
		$serverip = trim(fgets(STDIN));
		// �󂾂�����
		if (empty($serverip)){
			$serverip = getHostByName(getHostName());
		}
		echo "\n";

		echo '    3. �K�v�ȏꍇ�ATVRemotePlus �����p����|�[�g��ݒ肵�Ă��������B'."\n";
		echo "\n";
		echo '      �ʏ�́A�u���E�U�� URL ������ http://'.$serverip.':8000 �ŃA�N�Z�X�ł��܂��B'."\n";
		echo '      ���� 8000 �̔ԍ���ς������ꍇ�́A�|�[�g�ԍ�����͂��Ă��������B'."\n";
		echo '      HTTPS �ڑ����̓|�[�g�ԍ��� �����Őݒ肵���ԍ� + 100 �ɂȂ�܂��B'."\n";
		echo "\n";
		echo '      �悭�킩��Ȃ��ꍇ�́AEnter �L�[�������A���ɐi��ł��������B'."\n";
		echo "\n";
		echo '      ���p�|�[�g�ԍ��F';
		// TVRemotePlus���ғ�������|�[�g
		$http_port = trim(fgets(STDIN));
		// �󂾂�����
		if (empty($http_port)){
			$http_port = 8000;
		}
		$https_port = $http_port + 100; // SSL�p�|�[�g
		echo "\n";

		echo '    4. ���g���� TVTest �� BonDriver �� 32bit �ł����H 64bit �ł����H'."\n";
		echo "\n";
		echo '      32bit �̏ꍇ�� 1 �A64bit �̏ꍇ�� 2 �Ɠ��͂��Ă��������B'."\n";
		echo '      ���̐ݒ�� 32bit �ŁE64bit �łǂ���� TSTask ���g���������܂�܂��B'."\n";
		echo '      �C���X�g�[���I����A���g���� TVTest �� BonDriver �� ch2 �t�@�C����'."\n";
		echo '      '.$serverroot.'/bin/TSTask/BonDriver/ �ɃR�s�[���Ă��������B'."\n";
		echo "\n";
		echo '      Enter �L�[�Ŏ��ɐi�ޏꍇ�A������ 32bit �� TSTask ��I�����܂��B'."\n";
		echo "\n";
		echo '      TVTest �� BonDriver�F';
		// TVTest��BonDriver
		$bondriver = trim(fgets(STDIN));
		// ����
		if ($bondriver != 2) $bondriver = 1;
		echo "\n";

		echo '    5. EDCB Material WebUI (EMWUI) �� API ������ URL ����͂��Ă��������B'."\n";
		echo "\n";
		echo '      �ʏ�� http://(EDCB�̂���PC��IP�A�h���X):5510/api/ �ɂȂ��Ă��܂��B'."\n";
		echo '      EDCB Material WebUI �̃|�[�g��t�H���_�\����ύX���Ă�����A'."\n";
		echo '      EDCB ���ʂ� PC �ɓ����Ă���ꍇ�́A�K�X�ݒ��ύX���Ă��������B'."\n";
		echo "\n";
		echo '      Enter �L�[�Ŏ��ɐi�ޏꍇ�A���� PC �� EDCB ����������Ă���Ɖ��肵�A'."\n";
		echo '      ������ http://'.$serverip.':5510/api/ �ɐݒ肵�܂��B'."\n";
		echo '      ���̐ݒ�� �� �T�C�h���j���[ �� �ݒ� �� ���ݒ� ������ύX�ł��܂��B'."\n";
		echo "\n";
		echo '      EMWUI �� API ������ URL�F';
		// TVTest��BonDriver
		$EDCB_http_url = trim(fgets(STDIN));
		// ����
		if (empty($EDCB_http_url)){
			$EDCB_http_url = 'http://'.$serverip.':5510/api/';
		}
		echo "\n";

		echo '    6. �^��t�@�C���̂���t�H���_���w�肵�܂��B'."\n";
		echo "\n";
		echo '      �t�H���_���h���b�O&�h���b�v���邩�A�t�H���_�p�X����͂��Ă��������B'."\n";
		echo '      �Ȃ��A����ȃp�X (UNC�p�X��) �̏ꍇ�A����ɓ��삵�Ȃ��\��������܂��B'."\n";
		echo "\n";
		echo '      ���̐ݒ�� �� �T�C�h���j���[ �� �ݒ� �� ���ݒ� ������ύX�ł��܂��B'."\n";
		echo "\n";
		echo '      �^��t�@�C���̂���t�H���_�F';
		// �^��t�@�C���̂���t�H���_
		$TSfile_dir = trim(fgets(STDIN));
		$TSfile_dir = str_replace('"', '', $TSfile_dir);
		echo "\n";
		// �󂾂�����
		if (empty($TSfile_dir)){
			while(empty($TSfile_dir)){
				echo '      ���͗�����ł��B������x���͂��Ă��������B'."\n";
				echo "\n";
				echo '      �^��t�@�C���̂���t�H���_�F';
				$TSfile_dir = trim(fgets(STDIN));
				$TSfile_dir = str_replace('"', '', $TSfile_dir);
				echo "\n";
			}
		}
		// �t�H���_���Ȃ�������
		if (!file_exists($TSfile_dir)){
			while(!file_exists($TSfile_dir)){
				echo '      �t�H���_�����݂��܂���B������x���͂��Ă��������B'."\n";
				echo "\n";
				echo '      �^��t�@�C���̂���t�H���_�F';
				$TSfile_dir = trim(fgets(STDIN));
				$TSfile_dir = str_replace('"', '', $TSfile_dir);
				echo "\n";
			}
		}
		// �u��
		$TSfile_dir = str_replace('\\', '/', $TSfile_dir);
		$TSfile_dir = rtrim($TSfile_dir, '/').'/';
	}

	echo '  -------------------------------------------------------------------'."\n";
	echo "\n";
	echo '    �C���X�g�[�����J�n���܂��B'."\n";
	echo "\n";
	echo '  -------------------------------------------------------------------'."\n";
	echo "\n";
	echo '    TVRemotePlus ���C���X�g�[�����Ă��܂��c'."\n";
	echo "\n";

	sleep(1); // 1�b

	// �t�H���_�����
	if_mkdir('/');
	if_copy ('/config.default.php', true);
	if_copy ('/createcert.bat', true);
	if_copy ('/LICENSE.txt', true);
	if_copy ('/README.md', true);
	if_copy ('/stream.bat', true);
	if_copy ('/bin', true);
	if_copy ('/data', true);
	if_copy ('/docs', true);
	if_copy ('/htdocs', true);
	if_copy ('/logs', true);
	if_copy ('/modules', true);

	// �ݒ�t�@�C��
	$tvrp_conf_file = $serverroot.'/config.php';
	$tvrp_default_file = $serverroot.'/config.default.php';

	// �V�K�C���X�g�[���݂̂̏���
	if ($update === false){

		// Apache �̐ݒ�t�@�C��
		$httpd_conf_file = $serverroot.'/bin/Apache/conf/httpd.conf';
		$httpd_default_file = $serverroot.'/bin/Apache/conf/httpd.default.conf';
		// PHP �̐ݒ�t�@�C��
		$php_ini_file = $serverroot.'/bin/PHP/php.ini';
		$php_default_file = $serverroot.'/bin/PHP/php.default.ini';

		// config.default.php �� config.php �ɃR�s�[
		copy($tvrp_default_file, $tvrp_conf_file);
		// httpd.default.conf �� httpd.conf �ɃR�s�[
		copy($httpd_default_file, $httpd_conf_file);
		// php.default.ini �� php.ini �ɃR�s�[
		copy($php_default_file, $php_ini_file);
		
		// TSTask �̃R�s�[
		if ($bondriver == 2){
			copy($serverroot.'/bin/TSTask/64bit/BonDriver_TSTask.dll', $serverroot.'/bin/TSTask/BonDriver_TSTask.dll');
			copy($serverroot.'/bin/TSTask/64bit/TSTask.exe', $serverroot.'/bin/TSTask/TSTask-tvrp.exe');
			copy($serverroot.'/bin/TSTask/64bit/TSTask.exe', $serverroot.'/bin/TSTask/TSTask_SPHD-tvrp.exe');
			copy($serverroot.'/bin/TSTask/64bit/TSTaskCentre.exe', $serverroot.'/bin/TSTask/TSTaskCentre-tvrp.exe');
		} else {
			copy($serverroot.'/bin/TSTask/32bit/BonDriver_TSTask.dll', $serverroot.'/bin/TSTask/BonDriver_TSTask.dll');
			copy($serverroot.'/bin/TSTask/32bit/TSTask.exe', $serverroot.'/bin/TSTask/TSTask-tvrp.exe');
			copy($serverroot.'/bin/TSTask/32bit/TSTask.exe', $serverroot.'/bin/TSTask/TSTask_SPHD-tvrp.exe');
			copy($serverroot.'/bin/TSTask/32bit/TSTaskCentre.exe', $serverroot.'/bin/TSTask/TSTaskCentre-tvrp.exe');
		}

		// ��Ԑݒ�t�@�C����������
		$jsonfile = $serverroot.'/data/settings.json';
		$json['1']['state'] = 'Offline';
		$json['1']['channel'] = '0';
		if (!file_exists($jsonfile)) file_put_contents($jsonfile, json_encode($json, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

		// TVRemotePlus �̐ݒ�t�@�C��
		$tvrp_conf = file_get_contents($tvrp_conf_file);
		// �u��
		$tvrp_conf = preg_replace('/^\$EDCB_http_url =.*/m', '$EDCB_http_url = \''.mb_convert_encoding($EDCB_http_url, 'UTF-8', 'SJIS, SJIS-WIN').'\';', $tvrp_conf);
		$tvrp_conf = preg_replace('/^\$TSfile_dir =.*/m', '$TSfile_dir = \''.mb_convert_encoding($TSfile_dir, 'UTF-8', 'SJIS, SJIS-WIN').'\';', $tvrp_conf);
		// ��������
		file_put_contents($tvrp_conf_file, $tvrp_conf);

		// Apache �̐ݒ�t�@�C��
		$httpd_conf = file_get_contents($httpd_conf_file);
		// �u��
		$httpd_conf = preg_replace("/Define SRVROOT.*/", 'Define SRVROOT "'.$serverroot.'"', $httpd_conf);
		$httpd_conf = preg_replace("/Define SRVIP.*/", 'Define SRVIP "'.$serverip.'"', $httpd_conf);
		$httpd_conf = preg_replace("/Define HTTP_PORT.*/", 'Define HTTP_PORT "'.$http_port.'"', $httpd_conf);
		$httpd_conf = preg_replace("/Define HTTPS_PORT.*/", 'Define HTTPS_PORT "'.$https_port.'"', $httpd_conf);
		// ��������
		file_put_contents($httpd_conf_file, $httpd_conf);

		// PHP �̐ݒ�t�@�C��
		$php_ini = file_get_contents($php_ini_file);
		// �u��
		$php_ini = preg_replace('/^extension_dir =.*/m', 'extension_dir = "'.mb_convert_encoding($serverroot.'/bin/PHP/ext/', 'UTF-8', 'SJIS, SJIS-WIN').'"', $php_ini);
		// ��������
		file_put_contents($php_ini_file, $php_ini);

		// HTTPS �ڑ��p�I���I���ؖ����̍쐬
		echo '    HTTPS �ڑ��p�̎��ȏ����ؖ������쐬���܂��B'."\n";
		echo "\n";
		echo '  -------------------------------------------------------------------'."\n";
		echo "\n";

		$cmd = 'pushd "'.str_replace('/', '\\', $serverroot).'\bin\Apache\bin\" && '.
			   '.\openssl.exe req -new -newkey rsa:2048 -nodes -config ..\conf\openssl.cnf -keyout ..\conf\server.key -out ..\conf\server.crt'.
			   ' -x509 -days 3650 -sha256 -subj "/C=JP/ST=Tokyo/O=TVRemotePlus/CN='.$serverip.'" -addext "subjectAltName = IP:127.0.0.1,IP:'.$serverip.'"';

		exec($cmd, $opt1, $return1);
		copy($serverroot.'/bin/Apache/conf/server.crt', $serverroot.'/htdocs/files/TVRemotePlus.crt');
		echo "\n";
		echo '  -------------------------------------------------------------------'."\n";
		echo "\n";
		if ($return1 == 0){
			echo '    ���ȏ����ؖ����𐳏�ɍ쐬���܂����B'."\n";
		} else {
			echo '    ���ȏ����ؖ����̍쐬�Ɏ��s���܂����c'."\n\n";
			echo '    ���ȏ����ؖ���������ɍ쐬����Ă��Ȃ��ꍇ�AApache �̋N���Ɏ��s���܂��B'."\n";
			echo '    �C���X�g�[����ɃR�s�[����Ă��� createcert.bat �����s���Ď��ȏ����ؖ������쐬���邩�A'."\n";
			echo '    �ăC���X�g�[�����A'.$serverroot.'/bin/Apache/conf/ �� server.crt �� server.key'."\n";
			echo '    ���쐬����Ă��邱�Ƃ��m�F���Ă��� TVRemotePlus ���N�����Ă��������B'."\n";
		}

		// �V���[�g�J�b�g�쐬
		// ���ɃV���[�g�J�b�g������ꍇ�͏㏑�����Ȃ��悤�V���[�g�J�b�g����ς���
		if (file_exists(getenv('USERPROFILE').'\Desktop\TVRemotePlus - launch.lnk')){
			$shortcut_file = '\Desktop\TVRemotePlus - launch (1).lnk';
			$shortcut_count = 1;
			while (file_exists(getenv('USERPROFILE').$shortcut_file)){
				if (file_exists(getenv('USERPROFILE').$shortcut_file)){
					$shortcut_count++;
					$shortcut_file = '\Desktop\TVRemotePlus - launch ('.$shortcut_count.').lnk';
				}
			}
		} else {
			$shortcut_file = '\Desktop\TVRemotePlus - launch.lnk';
		}
		$powershell = '$shell = New-Object -ComObject WScript.Shell; '.
					  '$lnk = $shell.CreateShortcut(\"$Home'.$shortcut_file.'\"); '.
					  '$lnk.TargetPath = \"'.str_replace('/', '\\', $serverroot).'\bin\Apache\bin\httpd.exe\"; '.
					  '$lnk.WorkingDirectory = \"'.str_replace('/', '\\', $serverroot).'\bin\Apache\bin\"; '.
					  '$lnk.WindowStyle = 7; '.
					  '$lnk.Save()';
		exec('powershell -Command "'.$powershell.'"', $opt2, $return2);
		echo "\n";
		if ($return2 == 0) echo '    �V���[�g�J�b�g���쐬���܂����B'."\n";
		else echo '    �V���[�g�J�b�g�̍쐬�Ɏ��s���܂����c'."\n";
		
		echo "\n";

	// �A�b�v�f�[�g����
	} else {

		// �Â��ݒ�t�@�C����ǂݍ���
		require_once ($tvrp_conf_file);

		// config.default.php �� config.php �ɃR�s�[
		copy($tvrp_default_file, $tvrp_conf_file);

		// �ݒ��z��Ɋi�[
		@$config['quality_default'] = $quality_default;
		@$config['encoder_default'] = $encoder_default;
		@$config['BonDriver_default_T'] = $BonDriver_default_T;
		@$config['BonDriver_default_S'] = $BonDriver_default_S;
		@$config['stream_current_live'] = $stream_current_live;
		@$config['stream_current_file'] = $stream_current_file;
		@$config['subtitle_default'] = $subtitle_default;
		@$config['subtitle_file_default'] = $subtitle_file_default;
		@$config['TSfile_dir'] = $TSfile_dir;
		@$config['TSinfo_dir'] = $TSinfo_dir;
		@$config['EDCB_http_url'] = $EDCB_http_url;
		@$config['reverse_proxy_url'] = $reverse_proxy_url;
		@$config['setting_hide'] = $setting_hide;
		@$config['silent'] = $silent;
		@$config['history_keep'] = $history_keep;
		@$config['update_confirm'] = $update_confirm;
		@$config['nicologin_mail'] = $nicologin_mail;
		@$config['nicologin_password'] = $nicologin_password;
		@$config['tweet_time'] = $tweet_time;
		@$config['tweet_upload'] = $tweet_upload;
		@$config['tweet_delete'] = $tweet_delete;
		@$config['CONSUMER_KEY'] = $CONSUMER_KEY;
		@$config['CONSUMER_SECRET'] = $CONSUMER_SECRET;
		@$config['basicauth'] = $basicauth;
		@$config['basicauth_user'] = $basicauth_user;
		@$config['basicauth_password'] = $basicauth_password;
		@$config['setting_redirect'] = $setting_redirect;
		@$config['encoder_log'] = $encoder_log;
		@$config['encoder_window'] = $encoder_window;
		@$config['TSTask_shutdown'] = $TSTask_shutdown;
		@$config['udp_port'] = $udp_port;
		@$config['hlslive_time'] = $hlslive_time;
		@$config['hlsfile_time'] = $hlsfile_time;
		@$config['hlslive_list'] = $hlslive_list;


		// �V�����R�s�[�����ݒ�t�@�C���ɈȑO�̐ݒ���C���|�[�g����
		$tvrp_conf = file_get_contents($tvrp_conf_file);

		foreach ($config as $key => $value) {

			// ��łȂ����
			if (!empty($value)){

				// �V���O���N�H�[�e�[�V���������i�Z�L�����e�B�΍�j
				$value = str_replace('\'', '', $value);

				// ���l���ł�����̂͐��l�ɕϊ����Ă���
				if (is_numeric($value) and mb_substr($value, 0, 1) != '0'){
					$set = intval($value);
				} else {
					$set = '\''.strval($value).'\'';
				}

				// �o�b�N�X���b�V��(\)����������X���b�V���ɕϊ�
				if (strpos($set, '\\') !== false){
					$set = str_replace('\\', '/', $set);
				}
				
				// config.php ����������
				$tvrp_conf = preg_replace("/^\\$$key =.*;/m", '$'.$key.' = '.$set.';', $tvrp_conf); // �u��
				
			}
		}

		file_put_contents($tvrp_conf_file, $tvrp_conf); // ��������
	}

	echo '  -------------------------------------------------------------------'."\n";
	echo "\n";
	echo '    �C���X�g�[�����������܂����B'."\n";
	echo "\n";
	sleep(1); // 1�b

	// �V�K�C���X�g�[���݂̂̏���
	if ($update === false){
		echo '    �Z�b�g�A�b�v�͂܂��I����Ă��܂���B'."\n\n";
		echo '    BonDriver �� TVTest �̃`�����l���ݒ�t�@�C�� (.ch2) �� '."\n";
		echo '    '.$serverroot .'/bin/TSTask/BonDriver/ �ɖY�ꂸ�ɓ���Ă��������B'."\n\n";
		echo '    �I�������A�f�X�N�g�b�v�̃V���[�g�J�b�g���� TVRemotePlus ���N�����A'."\n";
		echo '    �u���E�U���� http://'.$serverip.':'.$http_port.'/ �փA�N�Z�X���܂��B'."\n";
		echo '    ���̌�A�� �T�C�h���j���[ �� �ݒ� �� ���ݒ� ����K�v�ȉӏ���ݒ肵�Ă��������B'."\n\n";
		echo '    PWA �@�\���g�p����ꍇ�́A�ݒ�y�[�W����_�E�����[�h�ł��鎩�ȏ����ؖ�����'."\n";
		echo '    ���炩���ߒ[���ɃC���X�g�[��������ŁA https://'.$serverip.':'.$https_port.'/ �ɃA�N�Z�X���Ă��������B'."\n";
		echo "\n";
	}

	echo '    �I������ɂ͉����L�[�������Ă��������B'."\n";
	echo "\n";
	echo '  -------------------------------------------------------------------'."\n";
	echo "\n";
	trim(fgets(STDIN));
