$(function () {
    if ($('.ctn-open').length == 0) {
        $('.ctn-receive').removeClass('hide');
    }

    $('.btn-receive').click(function () {
        layer.open({
            className: 'layer-receive',
            shadeClose: false,
            content: '<em style="background-image:' + $('.ctn-receive .header em').css('backgroundImage').replace('"', '') + '"></em>' +
            '<h1>恭喜你共获得100元</h1>' +
            '<h2>已拆得<span class="on">' + $('.ctn-receive .ctn-pick .title span.on:eq(0)').text() + '元</span></h2>' +
            '<button>去提现</button>'
        });

        $('.layer-receive button').click(function () {
            // timeobj.text('23:59:59.9');
            // maxtime = 86399900;
            // $('.ctn-open').addClass('hide');
            // $('.ctn-receive').removeClass('hide');
            layer.closeAll();
            location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
        })
    })

    $('.btn-link-app').click(function () {
        location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
    })

    if (is_weixn()) {
        $('.progress-bar button').click(function () {
            layer.open({
                content: '请前往APP提现',
                skin: 'msg',
                time: 3,
                end: function () {
                    location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
                }
            });
        })

        $('.btn-share').click(function () {
            open_share();
        })

        $('.btn-open').click(function () {
            $.ajax({
                url: '/home/activity/pick_new_bag_open',
                type: 'post',
                data: {from_uid: from_uid},
                success: function (res) {
                    var p = $('.ctn-bag');
                    if (res.code == 0) {
                        $('.ctn-open').addClass('hide');
                        p.removeClass('hide');

                        var e = $('.ctn-pick .receive').click(show_receive_layer);
                        timesp.push(e.find('time'));

                        var e = $('.ctn-pick .open').click(show_open_layer)
                        timesp.push(e.find('time'));

                        if (is_new) {
                            setTimeout(function () {
                                show_open_layer();
                            }, 2000);
                        } else {
                            if (!!wx_info && !wx_info.subscribe) {
                                var option = {
                                    shadeClose: false,
                                    className: 'layer-subscribe',
                                    content: '<div class="close-wrap"><i class="close"></i></div>' +
                                    '<img src="' + wx_info.qrcode + '">' +
                                    '<p class="tips">长按扫码关注领更多福利</p>'
                                }

                                layer.open(option);

                                $('.layui-m-layer button').click(function () {
                                    location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
                                })

                                $('.layui-m-layer .close').click(function () {
                                    layer.closeAll();
                                })
                            }
                        }
                    }

                    if (res.data && res.data.msg) {
                        p.find('.ctn-pick .header h3').text(res.data.msg);
                    }

                    if (res.msg) {
                        layer.open({content: res.msg, skin: 'msg', time: 3});
                    }
                }
            })
        })
    } else {
        $('.btn-share').click(function () {
            interactive.btn_click();
        })

        if (window['account']) {
            layer.open({
                className: 'layer-receive',
                shadeClose: false,
                content:
                '<h1>恭喜您已拆满</h1>' +
                '<h2 class="big">' + (account / 100) + '元现金</h2>' +
                '<h5>已自动到您的账户余额，快去提现吧</h5>' +
                '<button>去提现</button>'
            });

            $('.layer-receive button').click(function () {
                $.ajax({
                    url: '/home/activity/pick_new_bag_withdraw',
                    type: "post",
                    success: function (res) {
                        if (res.code == 0) {
                            layer.closeAll();
                            interactive.redPacketWithdraw(true);
                        }

                        if (res.msg) {
                            layer.open({content: res.msg, skin: 'msg', time: 3});
                        }
                    }
                })
            })
        }

        if (window['award']) {
            layer.open({
                className: 'layer-award',
                content:
                '<i class="close"></i>' +
                '<h1>帮拆金额翻提升10倍</h1>' +
                '<h5>你的好友' + award.nickname + '（新用户）帮拆后下载了APP，他帮你拆的金额已提升10倍！</h5>' +
                '<button>立即查看</button>'
            });

            $('.layer-award button').click(function () {
                layer.closeAll();
                $('.redpack button').click();
            })

            $('.layer-award .close').click(function () {
                layer.closeAll();
            })
        }
    }

    $('.redpack button').click(function () {
        var content = '';
        for (var i in open_record) {
            var v = open_record[i];
            content += '<li class="flex">' +
                '<em style="background-image: url(' + v.avator + ')"></em>' +
                '<div class="flex1">' + v.nickname + '</div>' +
                '<span class="on">帮拆' + (v.open_price / 100) + '元</span>' +
                '</li>';
        }

        layer.open({
            className: 'layer-open-record',
            content: '<ul>' + content + '</ul>' +
            '<button>发给好友，继续帮我拆</button>' +
            '<p class="award-tips">新APP用户可帮拆10倍以上金额</p>'
        })

        $('.layer-open-record button').click(function () {
            layer.closeAll();
            if (is_weixn()) open_share();
            else interactive.btn_click();
        })
    })

    $('.btn-cash-open').click(function () {
        if ($(this).attr('level') < 3) {
            show_upgrade_layer();
            $(this).attr('level', 5);
        } else {
            $.ajax({
                url: '/home/activity/pick_new_cash_open',
                type: "post",
                success: function (res) {
                    if (res.code == 102) {
                        show_upgrade_layer();
                        return false;
                    }

                    if (res.code == 0) {
                        $('.ctn-money').remove();
                    }

                    if (res.msg) {
                        layer.open({content: res.msg, skin: 'msg', time: 3});
                    }
                }
            })
        }
    })

    $('.ctn-goods ul').on('click', 'li', function () {
        var $this = $(this);
        var v = $this.attr('v').split('-');
        var t = $this.parent().attr('event');
        var img = $this.find('em').css('background-image');
        var data = {
            platform_type: t == 'pdd' ? 1 : 2,
            id: v[0],
            goods_id: v[1],
            name: $this.find('.title').text(),
            img: img.split("(")[1].split(")")[0],
            sales_num: v[2],
            price: v[3],
            commission: v[4],
            coupon_price: v[5]
        };

        if (is_weixn()) {
            $.get('/home/goods/quan_link_' + t, {id: data.id}, function (res) {
                if (res.code == 0) {
                    location.href = res.data.info.good_quan_link.url;
                } else {
                    if (res.msg) {
                        layer.open({content: res.msg, skin: 'msg', time: 3});
                    }
                }
            })

            return false;
        }

        interactive.jumpProductDetail(JSON.stringify(data));
    })
})

function show_upgrade_layer() {
    var m = $('.ctn-money li:eq(0) span.on').text().replace('元', '');
    var s = parseInt($('.income .title span.on').text());
    if (isNaN(s)) s = 0;

    layer.open({
        className: 'layer-upgrade',
        content: '<i>' + m + '</i>'
        + '<h1>升级VIP才可领取哦</h1>'
        + '<p class="tleft">领取后随时可提现到微信/支付宝，在' + active_time[0] + '~' + active_time[1]
        + '活动期间，每天都可领取现金并随时进行提现，目前你已错过' + s + '元，别再错过了哦</p>'
        + '<button>升级VIP</button>'
    })

    $('.layer-upgrade button').click(function () {
        layer.closeAll();
        interactive.showPayDialog();
    })
}

function show_open_layer() {
    var p = $('.ctn-bag .ctn-pick .open');
    var option = {
        shadeClose: false,
        className: 'layer-receive',
        content: '<div class="close-wrap"><i class="close"></i></div>' +
        '<em style="background-image:' + $('.ctn-receive .header em').css('backgroundImage').replace('"', '') + '"></em>' +
        '<h1>恭喜你拆到</h1>' +
        '<h2><span class="on">' + p.find('span b').text() + '元</span></h2>' +
        '<h3><time>' + p.find('time').text() + '</time>后失效</h3>' +
        '<button>去提现</button>'
    }

    var option_new = function () {
        $('.ctn-bag').addClass('hide');
        $('.ctn-flush').removeClass('hide');
        $('body').addClass('nobg');
        history.pushState({page: 'flush'}, 'flush');
    }

    if (!!wx_info && !wx_info.subscribe) {
        option.end = function () {
            var option_subscribe = {
                shadeClose: false,
                className: 'layer-subscribe',
                content: '<div class="close-wrap"><i class="close"></i></div>' +
                '<img src="' + wx_info.qrcode + '">' +
                '<p class="tips">长按扫码关注后提现哦</p>' +
                '<button>直接去APP提现（全免费）</button>'
            }

            if (is_new) option_subscribe.end = option_new;

            layer.open(option_subscribe);

            $('.layui-m-layer button').click(function () {
                location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
            })

            $('.layui-m-layer .close').click(function () {
                layer.closeAll();
            })
        };

        layer.open(option);

        $('.layui-m-layer button').click(function () {
            layer.closeAll();
        })
    } else {
        if (is_new) option.end = option_new;
        layer.open(option);

        $('.layui-m-layer button').click(function () {
            location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
        })
    }

    $('.layui-m-layer .close').click(function () {
        layer.closeAll();
    })

    timesp.push($('.layer-receive time'));
}

function show_receive_layer() {
    var p = $('.ctn-bag .ctn-pick .receive');
    var option = {
        shadeClose: false,
        className: 'layer-receive',
        content: '<div class="close-wrap"><i class="close"></i></div>' +
        '<em style="background-image:' + $('.ctn-receive .header em').css('backgroundImage').replace('"', '') + '"></em>' +
        '<h1>恭喜你领到</h1>' +
        '<h2><span class="on">' + p.find('span b').text() + '元</span></h2>' +
        '<h3><time>' + p.find('time').text() + '</time>后失效</h3>' +
        '<button>去提现</button>'
    };

    if (!!wx_info && !wx_info.subscribe) {
        option.end = function () {
            var option_subscribe = {
                shadeClose: false,
                className: 'layer-subscribe',
                content: '<div class="close-wrap"><i class="close"></i></div>' +
                '<img src="' + wx_info.qrcode + '">' +
                '<p class="tips">长按扫码关注后提现哦</p>' +
                '<button>直接去APP提现（全免费）</button>'
            }

            layer.open(option_subscribe);

            $('.layui-m-layer button').click(function () {
                location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
            })

            $('.layui-m-layer .close').click(function () {
                layer.closeAll();
            })
        };

        layer.open(option);

        $('.layui-m-layer button').click(function () {
            layer.closeAll();
        })
    } else {
        layer.open(option);

        $('.layui-m-layer button').click(function () {
            location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
        })
    }

    $('.layui-m-layer .close').click(function () {
        layer.closeAll();
    })

    timesp.push($('.layer-receive time'));
}

window.onpopstate = function (e) {
    if (e.state && e.state.page) {
        if (e.state.page == 'flush') {

        }
    } else {
        $('.ctn-flush').addClass('hide');
        $('body').removeClass('nobg');
        $('.ctn-bag').removeClass('hide').find('.receive').click();
    }
}

var timeobj = $('.redpack span.on');
var maxtime = timeobj.text().split(':');
maxtime = (maxtime[0] * 60 * 60 + maxtime[1] * 60) * 1000 + maxtime[2] * 1000;
var inittime = maxtime;
var limit = 100;
timer = setInterval("CountDown()", limit);
var timesp = $('.ctn-money li time');

var marquee_ul = $('.marquee').find('ul');
if (marquee_ul.length > 0) {
    var marquee_limit = marquee_ul.find('li')[0].offsetHeight + marquee_step;
    marquee_ul.after(marquee_ul.clone());
}

function CountDown() {
    if (maxtime >= 0) {
        var hours = Math.floor(maxtime / 3600 / 1000 % 24);
        var minutes = Math.floor(maxtime / 60 / 1000 % 60);
        var seconds = Math.floor(maxtime / 1000 % 60);
        var ms = Math.floor(maxtime % 1000 / 100);
        timeobj.text(("0" + hours).slice(-2) + ":" + ("0" + minutes).slice(-2) + ":" + ("0" + seconds).slice(-2) + "." + ms);
        maxtime -= limit;

        if (ms == 0) {
            if (seconds % 3 == 0) {
                var t = parseInt(marquee_ul.css('margin-top'));
                var mt = Math.abs(t) + marquee_limit;
                marquee_ul.animate({marginTop: -mt}, function () {
                    if (mt >= marquee_ul.height() + marquee_step) {
                        marquee_ul.css({marginTop: 0})
                    }
                });
            }

            if (timesp.length > 0) {
                timesp.each(function () {
                    var time_in = $(this).text().split(':');
                    time_in = (time_in[0] * 60 * 60 + time_in[1] * 60) * 1000 + time_in[2] * 1000 - 1000;
                    if (time_in >= 0) {
                        var hours = Math.floor(time_in / 3600 / 1000 % 24);
                        var minutes = Math.floor(time_in / 60 / 1000 % 60);
                        var seconds = Math.floor(time_in / 1000 % 60);
                        $(this).text(("0" + hours).slice(-2) + ":" + ("0" + minutes).slice(-2) + ":" + ("0" + seconds).slice(-2));
                    }
                })
            }
        }
    } else {
        clearInterval(timer);
        maxtime = inittime;
        timer = setInterval("CountDown()", limit);
    }
}

if (window['layui']) {
    $('.ctn-goods dt').click(function () {
        if ($(this).hasClass('on')) return false;

        var $this = $(this);
        var i = $this.index();
        var e = $this.parent().find('dt').removeClass('on').eq(i);
        var ul = $this.closest('.ctn-goods').find('ul').addClass('hide').eq(i).removeClass('hide');
        if (ul.find('li').length == 0) $('.layui-flow-more a')[0].click();
        else e.addClass('on');
    })

    page_jd = page_pdd = 1;
    layui.use('flow', function () {
        layui.flow.load({
            elem: '.more',
            done: function (page, next) {
                var p = $('.ctn-goods ul:visible');
                var e = p.attr('event');

                if (window['page_' + e] <= 0) {
                    next('', true);
                    return false;
                }

                if (!window['link_load_' + e]) {
                    next('', false);
                    return false;
                }

                var page_size = 20;
                var data = {};
                if (e == 'jd') data = {page: page_jd, page_size: page_size};
                else data = {offest: (page_pdd - 1) * page_size, limit: page_size};

                $.get(window['link_load_' + e], data, function (res) {
                    if (window['page_' + e] == 1 && res.data.goods_data.length > 0) $('.ctn-goods dt[event=' + e + ']').addClass('on');
                    window['page_' + e] = res.data.goods_data.length < page_size ? 0 : window['page_' + e] + 1;

                    var html = [];
                    layui.each(res.data.goods_data, function (index, v) {
                        html.push('<li v="' + v.id + '-' + v.goods_id + '-' + (v.sales_num ? v.sales_num : 0) + '-' + v.price + '-' + v.commission + '-' + v.coupon_price + '">' +
                            '            <em style="background-image: url(' + v.img + ')"></em>' +
                            '            <p class="title txt-ellipsis">' + v.name + '</p>' +
                            '            <div class="flex">' +
                            '                <span class="quan">' + v.coupon_price + '元</span>' +
                            (v.sales_num ? '<span class="sales flex1">销量' + v.sales_num + '</span>' : '') +
                            '            </div>' +
                            '            <div class="flex">' +
                            '                <span class="flex1">' + v.price + '</span>' +
                            '                <span class="txt-rmb txt-ellipsis">赚' + v.commission + '</span>' +
                            '            </div>' +
                            '        </li>'
                        )
                    })

                    next('', page_jd > 0 || page_pdd > 0);
                    if (html.length > 0) p.append(html);
                })
            }
        })
    })
}