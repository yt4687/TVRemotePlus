  $(function(){

    commentnumber = 0; // コメ番
    next_comeban = ''; // リクエストごとのコメ番（初回だけ空にする）
    var autoscroll = true;  // 自動スクロール中かどうか

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

                  // コメント数が500を超えたら
                  if (document.getElementsByClassName('comment-live').length >= 500){
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


    // ---------------------------------------------------------------------
    // ニコ生新実況に備えて

    async function newNicoJK() {

        // 視聴セッション構築用の WebSocket の URL
        // await で Ajax が完了するまで待つ
        const watchsession_url = (await $.ajax({
            url: '/api/jikkyov2',
            dataType: 'json',
            cache: false,
        }))['websocket_url'];

        // 視聴セッション構築用の WebSocket
        const watchsession = new WebSocket(watchsession_url);

        // WebSocket を開いたとき
        watchsession.addEventListener('open', function(event) {

            // 視聴セッションリクエスト
            watchsession.send(JSON.stringify({
                'type': 'startWatching',
                'data': {
                    'stream':{
                        'quality': 'super_high',
                        'protocol': 'hls',
                        'latency': 'low',
                        'chasePlay': false,
                    },
                    'room':{
                        'protocol': 'webSocket',
                        'commentable':true,
                    },
                    'reconnect': false,
                },
            }));
        });
      
        // WebSocket でメッセージを受信したとき
        watchsession.addEventListener('message', function(event){
            
            const message = JSON.parse(event.data);
            // console.log(message);

            if (message.type === 'room') {
        
                const websocket_url = message.data.messageServer.uri;
                const websocket_threadid = message.data.threadId;
                const websocket_postkey = message.data.yourPostKey;
        
                // コメントサーバーの WebSocket
                websocket = new WebSocket(websocket_url, 'msg.nicovideo.jp#json');
        
                // WebSocket を開いたとき
                websocket.addEventListener('open', function(event) {

                    // コメントの送信をリクエスト
                    websocket.send(JSON.stringify([
                        { 'ping': {'content': 'rs:0'} },
                        { 'ping': {'content': 'ps:0'} },
                        { 'ping': {'content': 'pf:0'} },
                        { 'ping': {'content': 'rf:0'} },
                        {
                            'thread':{
                                'thread': websocket_threadid,
                                'threadkey': websocket_postkey,
                                'version': '20061206',
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
                websocket.addEventListener('message', async event => {
                    
                    // コメントを取得
                    const comment = JSON.parse(event.data)['chat'];

                    // コメントがない or 広告用など特殊な場合は弾く
                    if (comment === undefined ||
                        comment['content'] === undefined ||
                        comment['content'].match(/\/[a-z]+ /)) {
                        return;
                    }

                    // 色・位置
                    let color = '#FFFFFF';  // 色のデフォルト
                    let position = 'right';  // 位置のデフォルト
                    if (comment['mail'] !== undefined && comment['mail'] !== null) {
                        const command = comment['mail'].replace('184', '').split(' ');
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
                    const time = moment.unix(comment['date']).format('HH:mm:ss');
                    const danmaku = {
                        text: comment['content'],
                        color: color,
                        type: position,
                    }

                    // 指定された秒数分待つ
                    await new Promise(r => setTimeout(r, settings['comment_delay'] * 1000));

                    // コメント一覧に表示する（ 768px 以上のみ）
                    if (document.body.clientWidth > 768){
                        document.querySelector('#comment-draw-box > tbody').insertAdjacentHTML('beforeend',`
                            <tr class="comment-live">
                                <td class="time" align="center">` + time + `</td>
                                <td class="comment">` + danmaku['text'] + `</td>
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
                        // console.log(event.data);
                        dp.danmaku.draw(danmaku);
                    }

                    // コメント数が 500 を超えたら
                    if (document.getElementsByClassName('comment-live').length >= 500){
                      // 古いコメントを削除
                      document.getElementsByClassName('comment-live')[0].remove();
                    }

                });
            }
        });

    };
   //  newNicoJK();  // しばらく封印

    /**
     * ニコニコの色指定を 16 進数カラーコードに置換する
     * @param {string} $color ニコニコの色指定
     * @return {string} 16 進数カラーコード
     */
    function getCommentColor($color) {
        switch ($color) {
            case 'red':
                return '#E54256';
            case 'pink':
                return '#FF8080';
            case 'orange':
                return '#FFC000';
            case 'yellow':
                return '#FFE133';
            case 'green':
                return '#64DD17';
            case 'cyan':
                return '#39CCFF';
            case 'blue':
                return '#0000FF';
            case 'purple':
                return '#D500F9';
            case 'black':
                return '#000000';
            case 'white':
                return '#FFFFFF';
            case 'white2':
                return '#CCCC99';
            case 'niconicowhite':
                return '#CCCC99';
            case 'red2':
                return '#CC0033';
            case 'truered':
                return '#CC0033';
            case 'pink2':
                return '#FF33CC';
            case 'orange2':
                return '#FF6600';
            case 'passionorange':
                return '#FF6600';
            case 'yellow2':
                return '#999900';
            case 'madyellow':
                return '#999900';
            case 'green2':
                return '#00CC66';
            case 'elementalgreen':
                return '#00CC66';
            case 'cyan2':
                return '#00CCCC';
            case 'blue2':
                return '#3399FF';
            case 'marineblue':
                return '#3399FF';
            case 'purple2':
                return '#6633CC';
            case 'nobleviolet':
                return '#6633CC';
            case 'black2':
                return '#666666';
            default:
                return null;
        }
    }

    /**
     * ニコニコの位置指定を DPlayer の位置指定に置換する
     * @param {string} $position ニコニコの位置指定
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
  
  });
