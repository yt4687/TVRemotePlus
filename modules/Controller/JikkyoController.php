<?php

require_once ('classloader.php');

class JikkyoController {
    

    /**
     * コンストラクタ
     */
    public function __construct() {

        require ('module.php');
        require ('require.php');

        // ストリーム番号を取得
        $stream = getStreamNumber($_SERVER['REQUEST_URI']);

        // 設定ファイル読み込み
        $settings = json_decode(file_get_contents($inifile), true);

        // ストリームが存在する
        if (isset($settings[$stream])) {

            // ストリーム状態が ON Air & チャンネルが 0 でない
            if ($settings[$stream]['state'] == 'ONAir' and intval($settings[$stream]['channel']) !== 0){ 
    
                // BonDriver とチャンネルを取得
                // 実際はチャンネルしか使わないのでこんなにいらない（👈技術的負債）
                list($BonDriver_dll, $BonDriver_dll_T, $BonDriver_dll_S, // BonDriver
                    $ch, $ch_T, $ch_S, $ch_CS, // チャンネル番号
                    $sid, $sid_T, $sid_S, $sid_CS, // SID
                    $onid, $onid_T, $onid_S, $onid_CS, // ONID(NID)
                    $tsid, $tsid_T, $tsid_S, $tsid_CS) // TSID
                    = initBonChannel($BonDriver_dir);
    
                // モデルを初期化
                $instance = new Jikkyo($nicologin_mail, $nicologin_password);
    
                // 実況 ID を取得
                if (isset($ch[$settings[$stream]['channel']])){
                    $nicojikkyo_id = $instance->getNicoJikkyoID($ch[$settings[$stream]['channel']]);
                } else if ($ch[intval($settings[$stream]['channel']).'_1']){
                    $nicojikkyo_id = $instance->getNicoJikkyoID($ch[intval($settings[$stream]['channel']).'_1']);
                } else {
                    $nicojikkyo_id = -2;
                }
    
                // 実況 ID が 1 以上であれば続行
                // 実況 ID が 1 以下は実況チャンネルが存在しない
                if ($nicojikkyo_id > 0) {
    
                    // 実況 ID からニコニコチャンネル ID を取得する
                    $nicochannel_id = $instance->getNicoChannelID($nicojikkyo_id);
    
                    // ニコニコチャンネル ID が存在する（=実況 ID がニコニコチャンネル上に存在する）
                    if ($nicochannel_id !== null) {
    
                        // ニコニコチャンネル ID から、現在放送中のニコ生の放送 ID を取得する
                        $nicolive_id = $instance->getNicoLiveID($nicochannel_id);
    
                        // 放送 ID が null でない（=現在放送中）
                        if ($nicolive_id !== null) {
    
                            // ニコ生のセッション情報を取得
                            $nicolive_session = $instance->getNicoliveSession($nicolive_id);
    
                        } else {
                            $message = '現在放送中の番組がありません。';
                        }
                    } else {
                        $message = 'このチャンネルの実況チャンネルは廃止されました。';
                    }
                } else {
                    $message = '実況チャンネルが存在しません。';
                }
            } else {
                $message = "Stream {$stream} は ON Air ではありません。";
            }
        } else {
            $message = "Stream {$stream} は存在しません。";
        }


        // ニコ生のセッション情報を取得できているか
        if (isset($nicolive_session)) {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'result' => 'success',
                'session' => $nicolive_session,
            ];

        // 何らかの要因でセッションを取得できなかった
        // エラーメッセージがあればそれを出力
        } else {

            // 出力
            $output = [
                'api' => 'jikkyo',
                'result' => 'error',
                'message' => (isset($message) ? $message : '不明なエラーが発生しました。'),
                'session' => [],
            ];

        }

        // JSON を表示
        header('content-type: application/json; charset=utf-8');
        echo json_encode($output, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
}
