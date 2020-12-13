
commentnumber = 0; // コメ番
next_comeban = ''; // リクエストごとのコメ番（初回だけ空にする）
var autoscroll = true;  // 自動スクロール中かどうか

$(function(){

    // jQuery で sleep
    function wait(sec) {
 
      // jQuery の Deferred を作成
      var objDef = new $.Deferred;
   
      setTimeout(function () {
        // sec 秒後に resolve() を実行して Promise する
        objDef.resolve(sec);
      }, sec * 1000);
   
      return objDef.promise();
    }
   

    // コメント取得
    let danmaku_request_success = true;
    setInterval((function status(){

      // 前回のリクエストが完了しているなら
      if (danmaku_request_success === true) {

        // リクエストを開始した
        danmaku_request_success = false;

        $.ajax({
          url: '/api/jikkyo/' + stream + '?min_comeban=' + next_comeban,
          dataType: 'json',
          cache: false,
        }).done(function(data) {
  
          // リクエストを完了した
          danmaku_request_success = true;
          
          // 次のコメ番を取得
          next_comeban = data['next_comeban'];

          var windowWidth = document.body.clientWidth;
          var danmaku = {};

          // 実況勢いを表示
          if (data['ikioi'] !== null && data['ikioi'] !== undefined){
            document.getElementById('ikioi').textContent = '実況勢い: ' + data['ikioi'];
          }

          if (data['data'] != null && data['data'][0]){ // data['data'] があれば (nullでなければ)

            // n秒後に実行(コメント遅延分)
            wait(settings['comment_delay']).done(function(){

              // コメントを無制限に表示 がオンの場合は全て流す
              // オフの場合は一度に最大5個のみ
              if (document.getElementsByClassName('dplayer-danunlimit-setting-input')[0].checked){
                var length = data['data'].length;
              } else {
                if (data['data'].length > 5){
                  var length = 5;
                } else {
                  var length = data['data'].length;
                }
              }

              for (i = 0; i < length; i++){

                // 代入する際にきちんと文字型とか数値型とかに変換してないとうまく代入できない
                danmaku['text'] = data['data'][i][4].toString();
                danmaku['color'] =　data['data'][i][2].toString();

                if (commentnumber >= parseInt(data['data'][i][5])) {
                  // console.log('【コメ番が古いため、描画をスキップします】')
                }

                // コメントが空でない && コメ番が新しくなっていれば (以前描画したコメントを再度描画しない)
                if (danmaku['text'] !== '' && (commentnumber < parseInt(data['data'][i][5]))){

                  // コメ番を更新
                  commentnumber = parseInt(data['data'][i][5]);

                  // 表示タイプを解析
                  if (data['data'][i][1] == 0){
                    danmaku['type'] = 0;
                  } else if (data['data'][i][1] == 1){
                    danmaku['type'] = 1;
                  } else if (data['data'][i][1] == 2){
                    danmaku['type'] = 2;
                  }

                  // console.log('For:' + i + ' DrawComment:' + danmaku['text'] + ' Color:' + danmaku['color'] + ' Type:' + danmaku['type']);

                  // 分と秒を計算
                  var now = new Date();
                  var hour = now.getHours(); // 時
                  var min = now.getMinutes(); // 分
                  var sec = now.getSeconds(); // 秒
                  if (hour < 10) {
                    hour = '0' + hour;
                  }
                  if (min < 10) {
                    min = '0' + min;
                  }
                  if (sec < 10) {
                    sec = '0' + sec;
                  }
                  var time = hour + ':' + min + ':' + sec;

                  // 手動スクロールでかつ完全にスクロールされている場合は自動スクロールに戻す
                  // 参考: https://developer.mozilla.org/ja/docs/Web/API/Element/scrollHeight
                  var commentbox = document.getElementById('comment-draw-box');
                  // console.log('Scroll: ' + Math.ceil(commentbox.scrollHeight - commentbox.scrollTop) + ' ScrollHeight: ' + commentbox.clientHeight)
                  if (autoscroll === false && Math.ceil(commentbox.scrollHeight - commentbox.scrollTop) - commentbox.clientHeight <= 1) {

                    autoscroll = true;

                    // ボタンを非表示
                    document.getElementById('comment-scroll').style.visibility = 'hidden';
                    document.getElementById('comment-scroll').style.opacity = 0;

                  }

                  // コメントをウインドウに出す
                  // 768px 以上のみ
                  if (windowWidth > 768){
                    document.querySelector('#comment-draw-box > tbody').insertAdjacentHTML('beforeend',
                        `<tr class="comment-live">
                           <td class="time" align="center">` + time + `</td>
                           <td class="comment">` + danmaku['text'] + `</td>
                         </tr>`);
                  }

                  // コメント描画 (再生時のみ)
                  if (!dp.video.paused){
                    dp.danmaku.draw(danmaku);
                  }

                  // コメント数が 500 を超えたら
                  if (document.getElementsByClassName('comment-live').length > 500){
                    // 古いコメントを削除
                    document.getElementsByClassName('comment-live')[0].parentNode.removeChild(document.getElementsByClassName('comment-live')[0]);
                  }

                }
              }

              // コメント欄を下にアニメーション
              // 768px 以上のみ
              if (windowWidth > 768 && autoscroll){

                // 要素を取得
                var $comment = $('.comment-live:last');
                var $commentbox = $('#comment-draw-box');

                // コメントまでスクロールする
                $comment.velocity('scroll', {
                  container: $commentbox,
                  duration: 150,
                  offset: -$commentbox.height() + $comment.height(),
                });

              }

            });
          }

        }).fail(function(data, status, error) {
  
          // リクエストを完了した
          danmaku_request_success = true;

          // エラーメッセージ
          message = 'failed to get comment. status: ' + status + '\nerror: ' + error.message;
          console.error(message);
  
        });
      }
      return status;
    }()),500);

    // マウスホイール or スワイプ or mousedown
    $('#comment-draw-box').on('wheel touchmove mousedown', function(){
      
      // 手動スクロール中
      autoscroll = false;

      // ボタンを表示
      document.getElementById('comment-scroll').style.visibility = 'visible';
      document.getElementById('comment-scroll').style.opacity = 1;

    });

    // コメントスクロールボタンがクリックされた時
    $('#comment-scroll').click(function(){
      
      // 自動スクロールに戻す
      autoscroll = true;

      // 要素を取得
      var $comment = $('.comment-live:last');
      var $commentbox = $('#comment-draw-box');

      // コメントまでスクロールする
      $comment.velocity('scroll', {
        container: $commentbox,
        duration: 300,
        offset: -$commentbox.height() + $comment.height(),
      });

      // ボタンを非表示
      document.getElementById('comment-scroll').style.visibility = 'hidden';
      document.getElementById('comment-scroll').style.opacity = 0;

    });
});


// ---------------------------------------------------------------------
// ニコ生新実況に備えて

function newNicoJKAPIBackend() {

    // 視聴セッション WebSocket
    let watchsession;

    // コメントセッション WebSocket
    let commentsession;

    // コメントセッション WebSocket に接続できたかどうか
    let commentsession_connectable;

    // コメントセッション WebSocket への接続情報など
    let commentsession_info;

    /**
     * 視聴セッションサーバーに接続し、コメントサーバーへの接続情報を取得する
     * 接続できたらコメントサーバーへの接続情報の入ったオブジェクトを、
     * 接続できなかったらエラーメッセージを返す
     * @return {array} [接続成功: true・接続失敗: false, コメントサーバーへの接続情報の入ったオブジェクト or エラーメッセージ]
     */
    async function getCommentServerInfo() {

        // 視聴セッション情報を取得
        // await で Ajax が完了するまで待つ
        const watchsession_info = await $.ajax({
            url: '/api/jikkyov2/' + stream,
            dataType: 'json',
            cache: false,
        });

        // 視聴セッション情報を取得できなかった
        if (watchsession_info.result === 'error') {
            return [false, watchsession_info.message];  // 接続失敗
        }

        // 視聴セッション WebSocket を開く
        watchsession = new WebSocket(watchsession_info.session.watchsession_url);

        // 視聴セッション WebSocket を開いたとき
        watchsession.addEventListener('open', (event) => {

            // 視聴セッションをリクエスト
            watchsession.send(JSON.stringify({
                'type': 'startWatching',
                'data': {
                    'stream': {
                        'quality': 'super_high',
                        'protocol': 'hls',
                        'latency': 'low',
                        'chasePlay': false,
                    },
                    'room': {
                        'protocol': 'webSocket',
                        'commentable': true,
                    },
                    'reconnect': false,
                },
            }));
        });

        // コメントサーバーへの接続情報を取得
        const commentsession_info = await new Promise((resolve, reject) => {

            // 視聴セッション WebSocket でメッセージを受信したとき
            watchsession.addEventListener('message', (event) => {

                // 受信したメッセージ
                const message = JSON.parse(event.data);

                switch (message.type) {

                    // ping-pong
                    case 'ping':

                        // pong を返してセッションを維持する
                        // 送り返さなかった場合勝手にセッションが閉じられる
                        watchsession.send(JSON.stringify({
                            'type': 'pong',
                        }));

                    break;

                    // 座席情報
                    case 'seat':

                        // keepIntervalSec の秒数ごとに keepSeat を送信して座席を維持する
                        setInterval(() => {
                            watchsession.send(JSON.stringify({
                                'type': 'keepSeat',
                            }));
                        }, message.data.keepIntervalSec * 1000);

                    break;

                    // 部屋情報（実際には統合されていて、全てアリーナ扱いになっている）
                    case 'room':

                        // コメントサーバーへの接続情報の入ったオブジェクトを返す
                        // デバッグ用で実際には使わないものもある
                        resolve({
                            // 視聴セッション情報
                            'title': watchsession_info.session.title,
                            'begintime': watchsession_info.session.begintime,
                            'endtime': watchsession_info.session.endtime,
                            'live_id': watchsession_info.session.live_id,
                            'user_id': watchsession_info.session.user_id,
                            'user_type': watchsession_info.session.user_type,
                            'is_login': watchsession_info.session.is_login,
                            'watchsession_url': watchsession_info.session.watchsession_url,
                            // コメントサーバーへの接続情報
                            'thread_id': message.data.threadId,
                            'postkey': (message.data.yourPostKey ? message.data.yourPostKey : null),
                            'commentsession_url': message.data.messageServer.uri,
                        });

                    break;
                }
            });
        });

        return [true, commentsession_info];
    }

    /**
     * コメントを受信・描画する
     * @param {object} options DPlayer から渡されるコールバック等が入ったオブジェクト
     */
    function receiveComment(options) {

        // 接続可能ならコメントサーバーに接続
        if (commentsession_connectable) {

            // コメントサーバーの WebSocket
            commentsession = new WebSocket(commentsession_info.commentsession_url, 'msg.nicovideo.jp#json');

            // WebSocket を開いたとき
            commentsession.addEventListener('open', (event) => {

                // コメントの送信をリクエスト
                commentsession.send(JSON.stringify([
                    { 'ping': {'content': 'rs:0'} },
                    { 'ping': {'content': 'ps:0'} },
                    { 'ping': {'content': 'pf:0'} },
                    { 'ping': {'content': 'rf:0'} },
                    {
                        'thread':{
                            'thread': commentsession_info.thread_id,
                            'threadkey': commentsession_info.postkey,
                            'version': '20061206',
                            'user_id': commentsession_info.user_id,
                            'res_from': 0,
                            'with_global': 1,
                            'scores': 1,
                            'nicoru': 0,
                        }
                    },
                ]));
            });

            // WebSocket でメッセージを受信したとき
            // コメントを描画する
            commentsession.addEventListener('message', async (event) => {

                // コメント送信リクエストの結果
                if (JSON.parse(event.data).thread !== undefined) {

                    // スレッド情報
                    const thread = JSON.parse(event.data).thread;

                    // リクエスト成功
                    if (thread.resultcode === 0) {

                        // 接続成功のコールバックを DPlayer に通知
                        console.log(commentsession_info);
                        options.success([{}]);  // 空のコメントを入れておく

                    // リクエスト失敗
                    } else {

                        // 接続失敗のコールバックを DPlayer に通知
                        const message = 'コメントサーバーに接続できませんでした。';
                        console.error('Error: ' + message);
                        options.error(message);  // エラーメッセージを送信
                    }
                }

                // コメントを取得
                const comment = JSON.parse(event.data).chat;

                // コメントがない or 広告用など特殊な場合は弾く
                if (comment === undefined ||
                    comment.content === undefined ||
                    comment.content.match(/\/[a-z]+ /)) {
                    return;
                }

                // 自分のコメントも表示しない
                if (comment.yourpost && comment.yourpost === 1) {
                    return;
                }

                // 色・位置
                let color = '#FFFFFF';  // 色のデフォルト
                let position = 'right';  // 位置のデフォルト
                if (comment.mail !== undefined && comment.mail !== null) {
                    const command = comment.mail.replace('184', '').split(' ');
                    for (const item of command) {
                        if (getCommentColor(item) !== null) {
                            color = getCommentColor(item);
                        }
                        if (getCommentPosition(item) !== null) {
                            position = getCommentPosition(item);
                        }
                    }
                }

                // 描画用の配列に変換
                const time = moment.unix(comment.date).format('HH:mm:ss');
                const danmaku = {
                    text: comment.content,
                    color: color,
                    type: position,
                }

                // HLS 配信に伴う遅延（指定された秒数）分待ってから描画
                await new Promise(r => setTimeout(r, settings.comment_delay * 1000));

                // コメント一覧に表示する（ 768px 以上のみ）
                if (document.body.clientWidth > 768){
                    document.querySelector('#comment-draw-box > tbody').insertAdjacentHTML('beforeend',`
                        <tr class="comment-live">
                            <td class="time" align="center">` + time + `</td>
                            <td class="comment">` + danmaku.text + `</td>
                        </tr>`
                    );

                    // スクロールする（自動スクロールが有効な場合のみ）
                    // ゆくゆく全部 behavior: 'smooth' に書き換えたい（ JS 使うと重いので）
                    if (autoscroll) {
                        const comment_draw_box = document.querySelector('#comment-draw-box');
                        comment_draw_box.scroll({
                            top: comment_draw_box.scrollHeight,
                            left: 0,
                            behavior: 'smooth',
                        });
                    }
                }

                // コメント描画 (再生時のみ)
                if (!dp.video.paused){
                    dp.danmaku.draw(danmaku);
                }

                // コメント数が 500 を超えたら
                if (document.getElementsByClassName('comment-live').length > 500){

                    // 古いコメントを削除
                    document.getElementsByClassName('comment-live')[0].remove();
                }
            });

        // 接続不能
        } else {

            // 接続失敗のコールバックを DPlayer に通知
            console.error('Error: ' + commentsession_info);
            options.error(commentsession_info);  // エラーメッセージを送信
        }
    }

    /**
     * コメントを送信する
     * @param {object} options DPlayer から渡されるコールバック等が入ったオブジェクト
     */
    function sendComment(options) {

        // 色
        const color_table = {
            '16777215': 'white',
            '15024726': 'red',
            '16769331': 'yellow',
            '6610199': 'green',
            '3788031': 'cyan',
            '13959417': 'purple',
        };

        // 位置
        const position_table = {
            '0': 'naka',
            '1': 'ue',
            '2': 'shita',
        };

        // vpos を計算 (10ミリ秒単位)
        const vpos = Math.floor(new Date().getTime() / 10) - (commentsession_info.begintime * 100);

        // コメントを送信
        watchsession.send(JSON.stringify({
            'type': 'postComment',
            'data': {
                'text': options.data.text,
                'color': color_table[options.data.color.toString()],
                'position': position_table[options.data.type.toString()],
                'vpos': vpos,
                'isAnonymous': true,
            }
        }));

        // コメント送信のレスポンス
        // onmessage なのはピンポイントでイベントを無効化できるため
        watchsession.onmessage = (event) => {

            // 受信したメッセージ
            const message = JSON.parse(event.data);
            
            switch (message.type) {

                // postCommentResult
                // これが送られてくる → コメント送信に成功している
                case 'postCommentResult':

                    // コメント成功のコールバックを DPlayer に通知
                    options.success();
                    
                    // イベントを解除
                    watchsession.onmessage = null;

                break;

                // error
                // コメント送信直後に error が送られてきた → コメント送信に失敗している
                case 'error':

                    // エラーメッセージ
                    let error;
                    switch (message.data.code) {
                        
                        case 'INVALID_MESSAGE':
                            error = 'コメント内容が不正です。';
                            break;

                        case 'COMMENT_POST_NOT_ALLOWED':
                            error = 'コメントするにはニコニコにログインしてください。';
                            break;

                        default:
                            error = `コメントの送信に失敗しました… (${message.data.code})`;
                            break;
                    }

                    // コメント失敗のコールバックを DPlayer に通知
                    options.error(error);
                    
                    // イベントを解除
                    watchsession.onmessage = null;
                    
                break;
            }
        }
    }

    /**
     * ニコニコの色指定を 16 進数カラーコードに置換する
     * @param {string} color ニコニコの色指定
     * @return {string} 16 進数カラーコード
     */
    function getCommentColor(color) {
        const color_table = {
            'red': '#E54256',
            'pink': '#FF8080',
            'orange': '#FFC000',
            'yellow': '#FFE133',
            'green': '#64DD17',
            'cyan': '#39CCFF',
            'blue': '#0000FF',
            'purple': '#D500F9',
            'black': '#000000',
            'white': '#FFFFFF',
            'white2': '#CCCC99',
            'niconicowhite': '#CCCC99',
            'red2': '#CC0033',
            'truered': '#CC0033',
            'pink2': '#FF33CC',
            'orange2': '#FF6600',
            'passionorange': '#FF6600',
            'yellow2': '#999900',
            'madyellow': '#999900',
            'green2': '#00CC66',
            'elementalgreen': '#00CC66',
            'cyan2': '#00CCCC',
            'blue2': '#3399FF',
            'marineblue': '#3399FF',
            'purple2': '#6633CC',
            'nobleviolet': '#6633CC',
            'black2': '#666666',
        };
        if (color_table[color] !== undefined) {
            return color_table[color];
        } else {
            return null;
        }
    }

    /**
     * ニコニコの位置指定を DPlayer の位置指定に置換する
     * @param {string} position ニコニコの位置指定
     * @return {string} DPlayer の位置指定
     */
    function getCommentPosition(position) {
        switch (position) {
            case 'ue':
                return 'top';
            case 'naka':
                return 'right';
            case 'shita':
                return 'bottom';
            default:
                return null;
        }
    }

    // ページを閉じる/移動する前に WebSocket を閉じる
    // しなくても勝手に閉じられる気はするけど一応
    window.addEventListener('beforeunload', function () {
        watchsession.close();
        commentsession.close();
    });

    return {

        // コメント受信時
        // 正確には最初のプレイヤー読み込み時のみ発火
        read: (options) => {

            // コメントサーバーへの接続情報を取得してから
            getCommentServerInfo().then(([commentsession_connectable_, commentsession_info_]) => {

                // 他からも見れるように上のスコープに配置
                commentsession_connectable = commentsession_connectable_;
                commentsession_info = commentsession_info_;

                // コメントを受信・描画する
                receiveComment(options);
            });
        },

        // コメント送信時
        send: (options) => {

            // コメントを送信する
            sendComment(options);
        }
    }
}
