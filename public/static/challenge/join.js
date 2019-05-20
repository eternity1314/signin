next_id = -1;

$(function () {
    $(".btn-join").click(function () {
        open_join_bar('challenge_join');
    })

    $(".act-signin").click(signin)

    $(".btn-change").click(function () {
        if (next_id == 0) return false;
        next_id = 0;
        $.get("/home/challenge/room_change", {}, function (res) {
            var html = [];
            for (var i in res.data.list) {
                var e = $(`<a class="flex">
                    <em></em>
                    <span class="flex1">
                        <p></p>
                        <p></p>
                    </span>
                </a>`);
                html.push(e);
                var item = res.data.list[i];
                e.attr('href', '/?s=home/challenge/join&room_id=' + item.room_id);
                e.find('em').css('background-image', 'url(' + item.avator + ')');
                var t = e.find('p');
                t.eq(0).text(item.title);
                if (item.is_leader) t.eq(0).append('<i></i>');
                t.eq(1).text('有进行中的挑战');
            }

            e = $(".layer-change").removeClass('hide');
            if (html.length > 0) e.prepend(html);
        });
    })

    $(".more").click(function () {
        window.webkit.messageHandlers.jumpEarly.postMessage(null)
        jumpEarly();
    })

    if (!is_weixn()) {
        $.ajax({url: "/static/js/interactive.js"});
    }

    var t = $(".top_tips div");
    var h = t.text();
    t.html('<div>' + h + '</div><div>' + h + '</div>');
    var tw = t.find("div:eq(0)").width();
    console.log(tw);
    var mf = 0;
    setInterval(function () {
        if (0 - mf - 100 >= tw) mf = 0;
        t.find('div:eq(0)').css('margin-left', --mf + 'px');
    }, 20);
})

function open_join_bar(name) {
    layer.open({
        type: 1,
        content: document.querySelector("#tpl-join-bar-" + name).innerHTML,//$(".join-bar." + name).clone().addClass("show")[0].outerHTML,
        anim: false
    });

    var e = $('.layui-m-layer .join-bar.' + name);

    e.find('[name=price]').change(function () {
        var price = parseFloat($(this).val());
        var day = parseInt(e.find('.day:eq(0)').text());

        e.find('.profit').text((price * day * income_rate / 100 + price).toFixed(2));
        e.find('.paid').text(price.toFixed(2));

        // console.log(price * (1 + income_rate / 100 + day));
        // console.log(price * day * (1 + income_rate / 100));
        // console.log(price + (price * day * income_rate / 100));
    })

    cbalance_select(e.find('.choose'));

    if (!is_weixn()) {
        e.find(".submit").html('<input type="hidden" name="pay_method" value="wx"><div class="pay-method">微信</div><div style="display:inline-block;width:62%">' + e.find("button").html() + '</div>');
        e.find(".pay-method").click(choose_paymethod)
    }

    return e;
}

function cbalance_select(obj) {
    $(obj).each(function () {
        if ($(this).hasClass("read")) return false;
        var t = $(this).find("label");
        var s = t.length;
        t.eq(parseInt(Math.random() * s)).click();
    })
}

function pay_ok() {
    layer.open({
        content: '参与成功，请明天准时过来打卡<br><br>我的族群可查看到详细状态',
        skin: 'msg',
        time: 3,
        end: function () {
            window.location = window.location;
        }
    });
}

function submit_callback(data) {
    if (data.data && data.data.challenge_id) pay_ok();

    var i = $(".join-bar").closest(".layui-m-layer").attr("index");
    layer.close(i);
}


signining = false;

function signin() {
    if (signining) return false;
    signining = true;

    if (!window['link_signin']) link_signin = "/home/challenge/signin";

    var data = {};
    if (window['room_id']) data.room_id = room_id;

    $.ajax({
        url: link_signin,
        data: data,
        type: "POST",
        success: function (data) {
            if (data.url) location.href = data.url;
            else if (data.msg) layer.open({content: data.msg, skin: 'msg', time: 3});
            signining = false;
        }
    })
}

function choose_paymethod() {
    var i = layer.open({
        type: 1,
        content: '<ul><li value="wx">微信</li><li value="ali">支付宝</li></ul>',
        className: 'layer-paymethod',
        anim: false
    });

    $(".layer-paymethod li").click(function () {
        $(".pay-method").text($(this).text());
        $("[name=pay_method]").val($(this).attr("value"));
        layer.close(i);
    })

    return false;
}