$(function () {
    $("body").on("click", ".submit", submit);

    if (window['wx_share']) {
        share_load();
    }
})

is_submit = false;

function submit(_this) {
    if (!_this) _this = this;
    var $this = $(_this);
    var form = $this.closest(".ajax-form");
    if (form.length == 0) form = $("body");
    var data = form.find('input,select,textarea').serialize();
    var action = form.attr("action");
    if (!action) {
        if (window['link_submit']) action = link_submit;
        else action = location.href;
    }
    var method = form.attr("method");
    if (!method) {
        if (window['method_submit']) method = method_submit;
        else method = "POST";
    }
    var callback = $this.attr("callback");

    pay_submit(action, data, method, callback);
}

function pay_submit(action, data, method, callback) {
    if (is_submit) return false;
    is_submit = true;

    $.ajax({
        url: action,
        data: data,
        type: method,
        success: function (data) {
            if (data.code && data.msg) {
                if (window["layer"]) layer.open({content: data.msg, skin: 'msg', time: 3});
                else alert(data.msg);
            }

            if (data.data) {
                if (data.data.pay_h5) location.href = data.data.pay_h5;
                else if (data.data.pay_param_wx) {
                    wxpay_jsapi_param = data.data.pay_param_wx;
                    to_pay();
                } else if (data.data.pay_html) {
                    $('.frame-pay,.layer-pay').remove();
                    $("body").append(data.data.pay_html);
                }
            }

            if (!callback) callback = 'submit_callback';
            if (typeof callback == "function") callback(data);
            else if (typeof callback == "string" && window[callback]) window[callback](data);
            // console.log(JSON.stringify(data));

            is_submit = false;
        }
    });
}

function to_pay() {
    // var frame = $("#frame_wxpay");
    // if (frame.length == 0) frame = $('<iframe id="frame_wxpay" src="/wxpay.html"></iframe>').appendTo('body');
    // else frame[0].contentWindow.to_pay();
    // console.log(frame);

    // if (location.pathname != '/') {
    //     var s = location.origin + '?s=' + location.pathname;
    //     layer.open({content: s, skin: 'msg', time: 3});
    //     // if (location.search) s += location.search;
    //     history.pushState({}, "wxpay", s);
    // }

    if (typeof callpay != "undefined") {
        callpay();
    } else {
        // setTimeout(function () {
        $.ajax({
            url: "/static/js/wxpay.js",
            success: function () {
                callpay();
            }
        });
        // }, 2000);
    }
}

function is_weixn() {
    return navigator.userAgent.toLowerCase().match(/MicroMessenger/i) == "micromessenger"
}

function share_load() {
    if (window['wx']) {
        share_init_wx();
    } else if (window['wx_config']) {
        $.ajax({
            url: "https://res.wx.qq.com/open/js/jweixin-1.0.0.js",
            dataType: "script",
            cache: true,
            success: function () {
                if (typeof wx_config == "string") wx_config = JSON.parse(wx_config);
                wx.config(wx_config);
                share_init_wx();
            }
        });
    } else {
        if (window['interactive']) {
            share_init_app();
        } else {
            $.ajax({
                url: "/static/js/interactive.js",
                success: function () {
                    share_init_app();
                }
            });
        }
    }
}

function share_init() {
    if (typeof wx_share == "string") wx_share = JSON.parse(wx_share);
    var share = {
        title: wx_share.title ? wx_share.title : "我正在培养早睡早起的习惯，一起来吧",
        desc: wx_share.desc ? wx_share.desc : "小伙伴们，别懒床了，早起打卡啦，一起瓜分早起动力金" + (window['prize_pool_balance'] ? "￥" + prize_pool_balance : ""),
        imgUrl: wx_share.imgUrl ? wx_share.imgUrl : location.origin + "/static/signin/img/qrcode_jiangnanhaoli.jpg",
        link: wx_share.link ? wx_share.link : location.origin
    };

    if (wx_share.success) share.success = wx_share.success;
    if (share.imgUrl.indexOf("://") < 0) share.imgUrl = location.origin + share.imgUrl;
    if (share.link.indexOf("://") < 0) share.link = location.origin + share.link;

    return share;
}

function share_init_wx() {
    var share = share_init();
    wx.ready(function () {
        wx.onMenuShareAppMessage(share);
        wx.onMenuShareTimeline(share);
    });
}

function share_init_app() {
    interactive.showShareButton(true);
}

function open_share() {
    var i = is_weixn();
    var html = '';
    if (i) html += '<div class="imgbox"></div><div class="txtbox">请点击右上角，选择“发送给朋友”或“分享到朋友圈”立即入账，可提现，秒到账</div>';
    html += '<div class="award icon icon-gold">分享成功，立即拆红包<br>可提现，秒到账</div>';
    if (!i) html += '<div class="btns"><button class="icon app-message"></button><button class="icon timeline"></button></div>';
    layer.open({
        type: '-share',
        content: html,
        anim: false
    })
}

function app_change(o, d) {
    var e = $('.frame-pay');
    if (e.length > 0) {
        out_trade_no = e.last().attr('out_trade_no');
        e.remove();
    }

    if (o == 'alipay' && d) {
        d = JSON.parse(decodeURI(d));
        var c = d.memo.ResultStatus;
    } else {
        if (!window['out_trade_no']) return false;
        var b = order_query(out_trade_no);
        if (b) var c = 9000;
        else var c = 4000;
    }

    if (c == 9000 || c == 8000) {
        if (window['pay_ok']) pay_ok(o);
        //if (window['layer']) layer.open({content: 'ok', skin: 'msg', time: 5});
    } else if (c == 6001) {
        if (window['pay_cancel']) pay_cancel(o);
        //if (window['layer']) layer.open({content: 'cancel', skin: 'msg', time: 5});
    } else {
        if (window['pay_fail']) pay_fail(o);
        //if (window['layer']) layer.open({content: 'fail', skin: 'msg', time: 5});
    }
}

function order_query(trade_no) {
    var b = false;
    $.ajax({
        url: '/home/genaral/order_query',
        data: {out_trade_no: trade_no},
        async: false,
        success: function (res) {
            b = !!!res.code;
        }
    })
    return b;
}
