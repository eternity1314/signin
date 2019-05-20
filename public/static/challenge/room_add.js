$(function () {
    parse_time();

    $("[name=day]").change(function () {
        parse_leader_rate();
    }).last().click();

    $(".stime right").click(open_time);
    $(".leader-rate article:eq(1) right").click(open_leader_rate);
    $("[name=recommend]").change(calc_total);
    // $("[name=room_fee]").change(calc_total).first().click();
    $(".create_fee article:eq(1) right").click(open_room_fee);

    $(".submit").click(function () {
        var e = $('[name=title]');
        if (e.val().trim() == '') {
            layer.open({content: '请输入早起族名', skin: 'msg', time: 3});
            e.focus();
            return false;
        }
    })

    $("body").on("click", ".select-time button", function () {
        var o = $(this).parent().find("input");
        var c = true;
        o.each(function (i) {
            var t = parseInt($(this).val());
            if (isNaN(t)) {
                layer.open({content: '时间有误', skin: 'msg', time: 3, type: 3});
                c = false;
                return false;
            }
            option.stime[i] = t;
        })

        if (c) {
            if (parse_time()) layer.closeAll();
        }
    }).on("click", ".select-leader-rate li", function () {
        parse_leader_rate($(this).index() - 1);
        layer.closeAll();
    }).on("click", ".select-room-fee li", function () {
        $("[name=room_fee]").val($(this).attr('mode'));
        calc_total();
        layer.closeAll();
    });

    var t = $(".top_tips div");
    var h = t.text();
    t.html('<div>' + h + '</div><div>' + h + '</div>');
    var tw = t.find("div:eq(0)").width();
    var mf = 0;
    setInterval(function () {
        if (0 - mf - 100 >= tw) mf = 0;
        t.find('div:eq(0)').css('margin-left', --mf + 'px');
    }, 20);
})

function open_time() {
    layer.open({
        title: '早起挑战打卡时间'
        , content: '<article>开始打卡时间:<input type="number" value="' + option.stime[0] + '">时'
        + '<input type="number" value="' + option.stime[1] + '">分</article>'
        + '<article>结束打卡时间:<input type="number" value="' + option.stime[2] + '">时'
        + '<input type="number" value="' + option.stime[3] + '">分</article>' +
        '<button>确认</button><i>备注：开始时间和结束时间间隔至少5分钟</i>'
        , className: 'select-time'
        , shadeClose: false
    });
}

function parse_time() {
    var err_msg = "时间有误，打卡时间是5:00~8:00！";

    if (5 > option.stime[0] || option.stime[0] >= 8) {
        layer.open({content: "开始" + err_msg, skin: 'msg', time: 3, type: 3});
        return false;
    }

    if (5 > option.stime[2] || option.stime[2] > 8) {
        layer.open({content: "结束" + err_msg, skin: 'msg', time: 3, type: 3});
        return false;
    }

    if (option.stime[1] >= 60 || option.stime[3] >= 60) {
        layer.open({content: "分钟有误！", skin: 'msg', time: 3, type: 3});
        return false;
    }

    var b = parseInt(option.stime[0] * 60 * 60 + option.stime[1] * 60);
    if (b < 18000 || b >= 28800) {
        layer.open({content: "开始" + err_msg, skin: 'msg', time: 3, type: 3});
        return false;
    }

    var e = parseInt(option.stime[2] * 60 * 60 + option.stime[3] * 60);
    if (e < 18000 || e > 28800) {
        layer.open({content: "结束" + err_msg, skin: 'msg', time: 3, type: 3});
        return false;
    }

    if (e - b < 300) {
        layer.open({content: '开始时间和结束时间间隔至少5分钟！', skin: 'msg', time: 3, type: 3});
        return false;
    }

    var b = ('0' + option.stime[1]).substr(-2);
    var e = ('0' + option.stime[3]).substr(-2);

    $(".stime article:eq(1) span").text(
        option.stime[0] + ':' + b + ' ~ ' + option.stime[2] + ':' + e
    );

    $("[name=btime]").val(option.stime[0] + b);
    $("[name=etime]").val(option.stime[2] + e);

    return true;
}

function open_leader_rate() {
    var content = '';
    var day = $("[name=day]:checked").val();
    var rate = option.info[day].leader_rate;
    for (var i in rate) {
        content += '<li>' + rate[i] + '%</li>';
    }

    layer.open({
        type: 1,
        content: '<div class="tips"><h5 class="tcenter mb-main">温馨提示</h5>该部分费用归你支配，结算后将直接转入您的账户余额当中，其中费用的30%作为族成员邀请好友进来参与的奖励金，以便让你的族成员不断地扩大，带来源源不断地收入。建议费率别太高以带动成员参与的早起动力，同时多发红包进一步刺激成员参与的积极性</div>'
        + '<ul><li>请选择设置费用比例</li>' + content + '</ul>'
        , anim: false
        , className: 'list select-leader-rate'
    });
}

function parse_leader_rate(i) {
    if (!i) i = 0;
    var day = $("[name=day]:checked").val();
    var rate = option.info[day].leader_rate[i];
    $("[name=leader_rate]").val(rate);
    $(".leader-rate article:eq(1) span").text(rate);

    calc_total();
}

function open_room_fee() {
    layer.open({
        type: 1,
        content: '<ul><li>请选择族群有效时长</li><li mode="month">包月</li><li mode="year">包年</li></ul>'
        , anim: false
        , className: 'list select-room-fee'
    });
}

function calc_total() {
    var day = parseInt($("[name=day]:checked").val());
    if (isNaN(day)) day = 0;
    var lose_rate = 0.03;
    if (day >= 21) {
        lose_rate = 0.12;
    } else if (day >= 7) {
        lose_rate = 0.06;
    }
    var recommend = parseInt($("[name=recommend]:checked").val());
    if (isNaN(recommend)) recommend = 0;
    var month = 1;
    var room_fee = $("[name=room_fee]").val();
    if (room_fee == 'year') {
        var room_fee_name = '年';
        month = 12;
    } else {
        var room_fee_name = '月';
    }

    var room_cost = option.info[day]['room_fee'][room_fee];
    if (event.target.tagName) $(".create_fee article:eq(1) span").text('￥' + room_cost + '/' + room_fee_name) // $(".room_fee article right").text(room_fee);

    var leader_rate = $("[name=leader_rate]").val();
    var income = 60000 * lose_rate * month * leader_rate / 100 * 0.7;
    var profit = income - room_cost;
    var paid = recommend;
    if (!window['free_type'] || (window['free_type'] && free_type == 'month' && room_fee == 'year')) {
        paid += room_cost;
    }

    // if (paid > 0 && $("[name=use_balance]").prop("checked")) {
    //     var user_balance = parseInt($(".user-balance:eq(0)").text() * 100) / 100;
    //     paid -= user_balance;
    //     if (paid < 0) {
    //         paid = 0;
    //     }
    // }

    $(".paid i").text(paid.toFixed(2));
    $(".profit i").text(profit.toFixed(2) + '/' + room_fee_name);

    $(".pay-bar .tips").unbind().click(function () {
        layer.open({
            title: '预计回报金额说明',
            content: '<p>预计回报金额根据每个人邀请能力的不同而有所不同，以平均数值计算，进入您族群打卡的人数300人计算，平均每人参与金额按200元/月计算，合计60000元，挑战失败率按' + (lose_rate * 100) + '%计算，按最低' + leader_rate + '%的费用算，则有' + income.toFixed(2) + '元的收入扣除创建族群费用每' + room_fee_name + '净赚' + profit.toFixed(2) + '元</p>'
            + '<p>邀请推广能力不同，参与人数也不同；邀请的人的属性不同，失败比例也不同，因此以上预计回报仅供参考，不做实际承诺</p>',
            btn: '我知道了',
            className: 'alert'
        });
    })
    //return {lose_rate: lose_rate, leader_rate: leader_rate, income: income, profit: profit};
}

function pay_ok(res) {
    var param;
    if (typeof res == 'string') {
        param = '&room_id=me';
    } else {
        param = '&room_id=' + res.data.room_id + '&redpack_id=' + res.data.redpack_id + '&leader_rate=' + $("[name=leader_rate]").val();
    }

    history.pushState({}, 'join', link_join + param);
    location.href = link_success + param;
}

function submit_callback(res) {
    if (res.data && res.data.room_id) pay_ok(res);
}