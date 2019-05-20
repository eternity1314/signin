$(function () {
    $(".otem").on("click", "li,li .dms .pic", function () {
        var $this = $(this);
        if ($this.is(".pic")) {
            if ($this.attr("href")) location.href = $this.attr("href");
            return false;
        }

        event_key = $this.attr("event");
        event_id = $this.attr("event_id");

        if (event_key == "system" || $this.find(".dms[href]").length > 0) return;

        if (typeof is_join != "undefined" && !is_join) {
            if (!$this.is("[ad]")) {
                //$(".overlay,.not-join." + event_key.split("_")[0]).addClass("visible");
                layer.open({content: '非族成员，无法领红包，请先加入', skin: 'msg', time: 3});
                return false;
            }
        }

        if (window["link_dms_" + event_key]) {
            var link = window["link_dms_" + event_key];
            var class_id = $this.attr("class_id");
            if (class_id) link += class_id;
            else link += event_id;
            location.href = link;
            return false;
        }

        if ($(this).attr("errmsg")) {
            layer.open({content: $(this).attr("errmsg"), skin: 'msg', time: 3});
            return false;
        }

        if ($(this).attr("status")) {
            if (event_key == "redpack") {
                location.href = link_redpack_info + "&redpack_id=" + event_id;
            }

            return false;
        }

        event_index = $this.index("li");
        // var data = JSON.parse($(this).attr("data"));
        if (event_key == "redpack") {
            open_redpack_draw();
            var obj = $(".draw");
            obj.find(".info").attr("href", link_redpack_info + "&redpack_id=" + event_id);
            obj.find("em").css("background-image", $this.find("em").css("background-image"));
            obj.find(".nickname").text($this.find(".nickname").text());
            if ($this.attr("errmsg")) {
                var msg = $this.attr("errmsg");
                obj.find("button").addClass("hide");
            } else {
                var msg = $this.find(".dms dl span").text();
                obj.find("button").removeClass("hide");
            }
            obj.find(".msg").text(msg);
            obj.addClass("visible");
        }
        // else if (event_key == "challenge_launch") {
        //     event_key = "challenge_accept";
        //     var obj = open_join_bar('challenge_accept');
        //     obj.find(".nickname").text($this.find(".nickname").text());
        //     obj.find(".day").text($this.find(".day").text());
        //     obj.find(".signin_time").text($this.find(".dms article section p:eq(0) span").text());
        //     obj.find("[name=challenge_id]").val(event_id);
        //     var s = obj.find("[name=price]");
        //     s.prop("checked", false);
        //     s.filter("[value=" + $this.find(".dms article section p:eq(1) span").text() + "]").prop("checked", true);
        // }
        else {
            return false;
        }
    })

    $(".choose [type=radio]").change(function () {
        calc_total();
    })

    $("body").on("click", ".choose.read", function () {
        layer.open({content: '公平起见，挑战契约金必须跟发起者一样哦', skin: 'msg', time: 3});
    })

    $(".prize .pbottom").click(function () {
        $(".prize .ptop a").get(0).click();
    })

    $(".tool-bar").on("click", ".close", function () {
        if ($(this).hasClass('pos')) {
            $(this).removeClass('pos').siblings().removeClass('hide')
        } else {
            $(this).addClass('pos').siblings().addClass('hide')
        }
    }).on("click", ".room-pic", function () {
        var pic = $(this).attr('pic');
        if (pic) {
            layer.open({
                title: document.title,
                content: '',
                styles: 'color:#fff',
                className: 'alert room-pic'
            });

            $('.layui-m-layer .alert.room-pic .layui-m-layercont').addClass('bg-main').css({
                'padding': '50%',
                'background-image': 'url(' + pic + ')'
            });
        } else {
            layer.open({content: '族长很懒，还没上传二维码', skin: 'msg', time: 3});
        }
    }).on("click", ".redpack", function () {
        if (!is_join) {
            layer.open({content: '非班级成员无法发班级红包', skin: 'msg', time: 3});
            return false;
        }
    })

    if (window["wx_share"]) {
        wx_share.success = function () {
            if (event_key == "redpack") {
                if ($(".draw").length > 0) {
                    $(".draw button").click();
                } else if ($(".prize").length > 0) {
                    $(".prize .pbottom button").click();
                }

                layer.close($(".layui-m-layer-share").attr('index'));
            }
        }
    }

    if (!is_weixn()) {
        $.ajax({url: "/static/js/interactive.js"});
    }
})

layui.use('flow', function () {
    var flow = layui.flow;
    flow.load({
        elem: '.otem',
        done: function (page, next) {
            $.get(link_load, {next_id: next_id}, function (res) {
                next_id = res.next_id;
                var html = [];
                layui.each(res.data, function (index, item) {
                    var li = $('<li class="i' + item.position + ' ' + item.event + '" event="' + item.event + '" event_id="' + item.event_id + '"></li>');
                    if (item.data.room_id) li.attr('room_id', item.data.room_id);
                    if (item.status != 1) li.attr('status', item.status);
                    if (item.errmsg) li.attr('errmsg', item.errmsg);

                    $('<em></em>').css("background-image", "url(" + item.avator + ")").appendTo(li);

                    var dms = $('<a class="dms"></a>');
                    if (item.event == 'system' && item['data']['link']) dms.attr('href', item['data']['link']);

                    switch (item.event) {
                        case 'redpack':
                            var e = $('<dl><span>' + item['data']['title'] + '</span><p>领取红包</p></dl><dt>微选生活红包<span>好习惯也能赚钱</span></dt>')
                            if (item.data.share) li.attr('share', '');
                            break;
                        case 'challenge_launch':
                            var e = $(`<dd>发起了维持<span class="day color-primary txt-day"></span>早起耐力挑战</dd>
                                <article>
                                <section>
                                <p>约定打卡<span></span></p>
                                <p>挑战金额<span class="txt-cny"></span></p>
                                <p>预计回报<span class="txt-cny"></span></p>
                                </section>
                                <div class="pic"></div>
                                </article>
                                <dt>点击接受挑战</dt>`);
                            e.find('.day').text(item.data.day);
                            var p = e.find('p span');
                            p.eq(0).text(item.data.stime);
                            p.eq(1).text((item.data.price / 100).toFixed());
                            p.eq(2).text(123.23);
                            break;
                        case 'challenge_accept':
                            var e = $(`<dd>接受了你的早起耐力挑战</dd>
                                <article>早睡早起好身体，记得要按约定日期和时间打卡哦，努力培养早睡早起的健康生活习惯，好习惯是需要坚持住的！</article>
                                <dt>戳我呀！看看是什么</dt>`)
                            break;
                        case 'challenge_room':
                            var e = `<dd>创建了维持<span class="day color-primary txt-day"></span>早起班级挑战</dd>
                                <article>约定打卡时间是，邀你一起来玩玩吧，有钱没钱，至少捧个人气吧</article>
                                <dt>戳我呀！看看是什么</dt>`;
                            break;
                        case 'system':
                            var e = '<dd>' + item.data.title + '</dd><article>' + item.data.content + '</article>';
                            if (item['data']['link']) e.append('<dt>戳我呀！看看是什么</dt>');
                            break;
                        default:
                            break;
                    }

                    $('<section><p class="nickname">' + item.nickname + '</p></section>').append(dms.append(e)).appendTo(li);

                    // console.log(li[0].outerHTML);
                    html.push('<time>' + item.add_time + '</time>');
                    html.push(li[0].outerHTML);
                });

                next(html.join(''), next_id > 0);
            });
        }
    });
});

function check() {
    var obj = $("[name=balance]:checked");
    var balance = parseFloat(obj.val());
    if (isNaN(balance) || balance < 1) {
        alert_tips("每日早起动力金最低一元");
        obj.focus();
        return false;
    }

    if (event_key == "challenge") {
        obj = $("[name=challenge_balance]:checked");
        var balance = parseFloat(obj.val());

        if (isNaN(balance) || balance < 1) {
            alert_tips("挑战契约金最低一元");
            return false;
        }
    } else if (event_key == "earlier_launch") {
        obj = $("[name=num]:checked");
        var balance = parseFloat(obj.val());
        if (isNaN(balance) || balance < 1) {
            alert_tips("挑战契约金最低一元");
            return false;
        }
    }

    return true;
}

function calc_total() {
    var parent = ".join-bar." + event_key;
    // if (window["event_index"]) {
    //     var data = $(".otem li").eq(event_index).attr("data");
    //     if (data) data = JSON.parse(data);
    //     else data = {};
    // }
    var user_balance = parseInt($(parent + " .user_balance").text() * 100) / 100;
    var balance = parseFloat($(parent + " [name=balance]:checked").val());
    if (isNaN(balance)) balance = 0;
    if (event_key.indexOf("challenge") >= 0) {
        var day = parseInt($(parent + " .day:eq(0)").text());
        if (isNaN(day)) day = 0;
    } else if (event_key.indexOf("earlier") >= 0) {
        var day = 1;
    }

    var paid = balance * day;
    var day_rate_all = 1 + day_rate;
    var profit = (balance * Math.pow(day_rate_all, day + 1) - balance * day_rate_all) / day_rate;
    if (profit > 0 && profit - paid < 0.01) profit = paid + 0.01;

    if (event_key.indexOf("challenge") >= 0) {
        var challenge_balance = parseFloat($(parent + " [name=challenge_balance]:checked").val());

        if (!isNaN(challenge_balance)) {
            paid += challenge_balance;

            var rate_income = 1.3;
            if (day > 3) {
                rate_income += (day - 3) * 0.1;
                if (rate_income > 2) rate_income = 2;
            }

            profit += challenge_balance * rate_income;
        }
    } else if (event_key.indexOf("earlier") >= 0) {
        var num = parseFloat($(parent + " [name=num]:checked").closest("label").find("em").text().replace("￥", ""));
        if (!isNaN(num)) {
            paid += num;
            profit += num * 2;
        }
    }

    if (paid > 0 && $(parent + " [name=pay_rmb]").prop("checked")) {
        paid -= user_balance;
        if (paid < 0) {
            paid = 0;
        }
    }

    if (paid > 0 && $(parent + " [name=pay_integral]").prop("checked")) {
        paid -= integral.available / 100;
        if (paid < 0) {
            paid = 0;
        }
    }

    $(parent + " .paid").text(paid.toFixed(2));
    $(parent + " .profit").text(profit.toFixed(2));
}

function pay_ok(data) {
    if (event_key == "challenge") {
        var panel = $(".join-panel");
        if (data.signin_date) panel.find(".sdate").text(data.signin_date);
        if (data.signin_time) panel.find(".stime").text(data.signin_time);

        $(".join-bar").removeClass("show");
        $(".overlay").removeAttr("hide").add(panel).addClass("visible");
    } else if (event_key == "earlier_permission") {
        alert_tips("开通成功", null, function () {
            location.href = location.href;
        });
    } else if (window["link_pay_ok_" + event_key]) {
        location.href = window["link_pay_ok_" + event_key];
    }
}

function open_join_bar(name) {
    layer.open({
        type: 1,
        content: $(".join-bar." + name).clone().addClass("show")[0].outerHTML,
        anim: false
    });
    return $('.layui-m-layer .join-bar.' + name);
}

function cbalance_select(obj) {
    $(obj).each(function () {
        if ($(this).hasClass("read")) return false;
        var t = $(this).find("label");
        var s = t.size();
        t.eq(parseInt(Math.random() * s)).click();
    })
}

function open_redpack_draw() {
    layer.open({
        type: 1,
        content: `<section class="draw">
        <i class="close"></i>
        <em style="background-image: url(/static/img/head.png)"></em>
        <div class="nickname"></div>
        <div class="redpacket_text">发一个红包，金额随机</div>
        <div class="msg"></div>
        <a class="info">查看大家手气</a>
        <button></button>
        </section>`,
        anim: false,
        className: 'layer-draw'
    })

    var e = $('.draw');
    e.find('.close').click(function () {
        location.href = location.href;
    })

    e.find('button').click(redpack_open);
}


function redpack_open() {
    var $this = $(this);
    var share = $(".otem li").eq(event_index).is("[share]");
    // var share = !$(".otem li").eq(event_index).is("[ad]");
    // if (!share) share = parseInt($this.attr("forward")) == 2;

    if (share) {
        share = $(".layui-m-layer-share").length == 0;
        if (share) {
            if (is_weixn()) {
                open_share();
            } else {
                layer.open({
                    type: '-share',
                    content: '',
                    anim: false,
                    shade: 'background-color: rgba(0,0,0,0)'
                });
                var p = JSON.parse(JSON.stringify(wx_share));
                p.success = 'wx_share.success()';
                interactive.btn_click(JSON.stringify(p));
            }

            return false;
        }
    }

    var data = {redpack_id: event_id};
    var assign_id = $this.attr("assign_id");
    if (assign_id) data.assign_id = assign_id;

    $this.addClass("animation");
    $this.prop("disabled", true);
    var date = new Date();
    $.ajax({
        url: link_redpack_draw,
        type: "post",
        data: data,
        success: function (data) {
            if (!data.status && data.info) {
                layer.open({content: data.msg, skin: 'msg', time: 3});
                return;
            }

            if (assign_id) {
                $this.prop("disabled", false);
                var ad = $(".prize .ptop a");
                var link = ad.attr("href");
                if (link && parseInt($this.attr("forward")) == 1) {
                    history_state = {"go": $(".draw .info").attr("href")};
                    history.pushState({}, "");
                    sessionStorage.setItem("history_state", JSON.stringify(history_state));
                    location.href = link;
                } else {
                    $(".draw .info").get(0).click();
                }
            } else {
                date = new Date() - date;
                if (date > 2000) {
                    redpack_draw(data);
                } else {
                    setTimeout(function () {
                        redpack_draw(data);
                    }, 2000 - date);
                }
            }
        }
    })
}

function redpack_draw(data) {
    var obj = $(".draw");
    obj.find("button").addClass("hide").removeClass("animation").prop("disabled", false);
    if (data.errmsg) {
        obj.find(".msg").text(data.errmsg);
        $(".otem > li").eq(event_index).attr("errmsg", data.errmsg);
    } else if (data.ad) {
        $(".draw").removeClass("visible");
        var prize = $(".prize");
        prize.find(".ptop a").css("background-image", "url(" + data.ad.ad_pic + ")").attr("href", data.ad.ad_link);
        prize.find(".pbottom .balance").text(data.assign.balance).addClass("txt-" + data.assign.event);
        prize.find(".pbottom .tips").text(data.assign.event == "rmb" ? "领取后立即到账" : "领取后积分可兑现");
        prize.find(".pbottom button").attr("assign_id", data.assign.assign_id).attr("forward", data.ad.forward);
        $(".overlay").add(prize).addClass("visible");
        var prize_cancel_second = 5;
        var prize_cancel = prize.find("em.cancel").text(prize_cancel_second).removeClass("close");
        prize_cancel_time = setInterval(function () {
            prize_cancel_second--;

            if (prize_cancel_second <= 0) {
                clearInterval(prize_cancel_time);
                prize_cancel.text("").addClass("close");
            } else {
                prize_cancel.text(prize_cancel_second);
            }
        }, 1000);
    } else {
        obj.find(".info").get(0).click();
    }
}