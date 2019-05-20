$(function () {
    $('.btn-rule, .rule-btn').click(function () {
        layer.open({
            className: 'alert layer-rule',
            shadeClose: false,
            content: '<i class="close"></i>' +
            '<fieldset><legend>活动规则</legend></fieldset>' +
            '<p>1、支付0.1元参与抢购活动，可让好友帮忙提高抢购成功率，成功率越高，越容易抢到商品</p>' +
            '<p>2、如商品抢购不成功，将全款退还你支付的金额到微选生活账户余额（即0.1元），每人参与抢购的商品数不限</p>' +
            '<p>3、抢购成功后，你可以选择免费拿走商品，也可将商品兑换成现金到微选生活账户余额，可随时提现到微信或支付宝</p>' +
            '<p>4、本活动在法律允许的范围内，解释权归平台方所有</p>'
        });

        $('.layui-m-layer .close').click(function () {
            layer.closeAll();
        })
    })

    $('.btn-link-app').click(function () {
        location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
    })

    if (is_weixn()) {
        $('.btn-share').click(function () {
            open_share();
        })

        $('.btn-buy').click(function () {
            location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan';
        })
    } else {
        if (window['award']) {
            layer_open_award();
        }

        $('.btn-share').click(function () {
            interactive.btn_click();
        })

        $('.btn-buy').click(function () {
            layer_open_buy();
        })
    }

    $('.ctn-goods ul').on('click', 'li', function () {
        var $this = $(this);
        var v = $this.attr('v').split('-');
        var img = $this.find('em').css('background-image');
        var data = {
            platform_type: 1,
            type: v[0],
            goods_id: v[1],
            id: v[2]
        };

        if (is_weixn()) {
            if (location.href.indexOf('/po_buy_goods_buying') > 0) {
                location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan';
                return false;
            }

            layer_open_download(data);
        } else {
            if (data.type == 1) {
                window.location.href = '/home/activity/po_buy_goods_detail?id=' + data.id;
            } else {
                window.location.href = '/home/activity/po_buy_goods_buying?id=' + data.id;
            }
        }
    })

    $('.help-btn').click(function () {
        if (is_submit) return false;
        is_submit = true;

        var $this = $(this);
        $.ajax({
            url: $this.attr('action'),
            data: {id: window['join_id']},
            type: 'post',
            success: function (data) {
                if (data.code && data.msg) {
                    $('.percent-num').parent().text(data.msg).removeClass('hide')
                    // if (window["layer"]) layer.open({content: data.msg, skin: 'msg', time: 3});
                    // else alert(data.msg);
                } else {
                    if (data.data) {
                        $('.percent-num').text(data.data.up_rate).parent().removeClass('hide').addClass('show');
                        $('.success-bar').children().children('span').text(data.data.success_rate);
                        if (data.data.is_award == 1) {
                            //layer_open_download();
                            //layer_open_receive();
                        }
                    }
                }

                $('.layer-download').removeClass('hide').find('li').click(function () {
                    layer.closeAll();
                    layer_open_receive($(this).find('span').text());
                });

                $('.can_help').addClass('hide');
                $('.no_can_help').removeClass('hide');
                // console.log(JSON.stringify(data));

                if (is_new) {
                    setTimeout(function () {
                        layer_open_open();
                    }, 2000);
                } else if (!!wx_info && !wx_info.subscribe) {
                    layer_open_subscribe_flush();
                } else {
                    layer_open_wxapp()
                }


                is_submit = false;
            }
        });
    })

    $(document).on('click', '.close', function () {
        layer.closeAll();
    })

    $('.top_goods').click(function () {
        $(window).scrollTop($('.ctn-goods').offset().top)
    })
})

// 商品信息
if (window['layui']) {
    page_pdd = 1;
    layui.use('flow', function () {
        layui.flow.load({
            elem: '.more',
            done: function (page, next) {
                var e = 'pdd';

                if (window['page_' + e] <= 0) {
                    next('', true);
                    return false;
                }

                if (!window['link_load_' + e]) {
                    next('', false);
                    return false;
                }

                var page_size = 20;
                var url = window['link_load_' + e] + 'page/' + page_pdd + '/page_size/' + page_size;

                $.get(url, {}, function (res) {
                    window['page_' + e] = res.data.result.goods_data.length < page_size ? 0 : window['page_' + e] + 1;

                    var html = [];
                    layui.each(res.data.result.goods_data, function (index, v) {
                        if (location.href.indexOf('home/activity/po_buy_goods?') == -1 && v.type == 2) {
                            return;
                        }
                        html.push('<li v="' + v.type + '-' + v.goods_id + '-' + v.id + '-' + v.goods_name + '-' + v.price + '-' + v.snap_up_count + '">' +
                            '            <em style="background-image: url(' + v.goods_img + ')">' +
                            '                ' + (v.type == 2 ? '<span class="tips-buying">抢购中</span>' : '') +
                            '            </em>' +
                            '            <div class="info-container">' +
                            '                <p class="title txt-ellipsis">' + v.goods_name + '</p>' +
                            '                <div class="flex">' +
                            '                    <span class="txt-snap_up"><span class="txt-snap_up_count">' + v.snap_up_count + '人</span>抢购成功</span>' +
                            '                </div>' +
                            '                <div class="flex">' +
                            '                    <span class="flex1 txt-price">￥' + v.price + '</span>' +
                            '                    <span class="txt-buying txt-ellipsis">0.1元购</span>' +
                            '                </div>' +
                            '            </div>' +
                            '        </li>'
                        )
                    })

                    next('', page_pdd > 0);
                    if (html.length > 0) $('.ctn-goods ul').append(html);
                })
            }
        })
    })
}

function layer_open_upgrade() {
    var option = {
        className: 'layer-upgrade alert',
        shadeClose: false,
        content: '<i class="close"></i>' +
        '<h3>VIP会员优先抢到</h3>' +
        '<p>每次抢购，当有很多用户与您抢购成功率相同时VIP会员可在抢购时比他们优先抢到</p>'
    }

    if (!window['level_promote']) {
        option.className += ' disabled';
        option.btn = ['你已是VIP会员'];
        option.yes = function () {
            return false;
        };
    } else {
        option.btn = ['升级VIP会员'];
        option.yes = function () {
            layer.closeAll();
            event_key = 'upgrade';

            if (is_weixn()) {
                pay_submit('/home/user/level_promote', {level: level_promote}, 'post');
            } else {
                interactive.showPayDialog();
            }
        }
    }

    layer.open(option);

    $('.layui-m-layer .close').click(function () {
        layer.closeAll();
    })
}

function layer_open_buy() {
    layer.open({
        className: 'layer-buy alert',
        shadeClose: false,
        content: '<i class="close"></i>' +
        '<h1>当前成功率太低，商品不容易抢到啊</h1>',
        btn: ['现在就抢', '提高成功率'],
        yes: function () {
            layer_open_buy_confirm();
        },
        no: function () {
            $('.btn-share:eq(0)').click();
        }
    });

    $('.layui-m-layer .close').click(function () {
        layer.closeAll();
    })
}

function layer_open_buy_confirm() {
    layer.open({
        className: 'layer-buy-confirm alert',
        shadeClose: false,
        content: '<h1>当前成功率太低，确定现在抢购吗?</h1>',
        btn: ['确定', '取消'],
        yes: function () {
            $.ajax({
                url: '/home/activity/point_one_buy_act',
                type: 'post',
                data: {id: id},
                success: function (res) {
                    if (!res.code) {
                        layer_open_buy_res(res);
                    } else {
                        if (res.msg) {
                            layer.open({content: res.msg, skin: 'msg', time: 3});
                        }
                    }
                }
            })
        }
    });
}

function layer_open_buy_res(res) {
    layer.open({
        className: 'layer-buy-loading',
        shadeClose: false,
        content: '<h1>当前成功率' + success_rate + '%，正在拼命抢购中...</h1>'
    });

    setTimeout(function () {
        if (res.data.is_buy_success == 1) { // 成功
            layer.open({
                className: 'layer-buy-res success',
                shadeClose: false,
                content: '<h1>恭喜你抢购成功</h1>' +
                '<h5>赶紧领取商品吧~</h5>' +
                '<button>好的</button>',
                end: function () {
                    var e = $('.hp-goods');
                    e.nextAll().remove();
                    e.after('<div class="buy-status big">抢购成功</div>' +
                        '<div class="btns">' +
                        '   <button class="btn-link-quan">直接购买，收货后返还你' + goods_price + '元</button>' +
                        '   <button class="btn-change">不购买，我要兑换成现金' + (goods_price * 0.7).toFixed(2) + '元</button>' +
                        '</div>');

                    $('.btn-link-quan').click(function () {
                        quan_link_pdd();
                    });
                    $('.btn-change').click(join_charge);
                    $('.help-container').addClass('hide');
                }
            });

            $('.layui-m-layer button').click(function () {
                layer.closeAll();
            })
        } else { // 失败
            layer.open({
                className: 'layer-buy-res fail',
                shadeClose: false,
                content: '<h1>抢购失败了，已退还付款</h1>' +
                '<h5>成功率越高，越容易抢到商品哦，下次再来试试吧</h5>' +
                '<div class="btn flex">' +
                '<button class="btn-refund">查看退款</button>' +
                '<button class="btn-goods">继续抢购</button>' +
                '</div>',
            });

            $('.layui-m-layer .btn-refund').click(function () {
                interactive.jumpMine();
            })

            $('.layui-m-layer .btn-goods').click(function () {
                location.href = '/home/activity/po_buy_goods?page=1&page_size=20';
            })
        }

    }, 4000)
}

function layer_open_download(param) {
    var option = {
        className: 'layer-download alert',
        shadeClose: false,
        content: '<i class="close"></i>' +
        '<h1>App才能0.1元购物哦，100%拿到商品</h1>' +
        '<p>全场0.1元就能买到，再送现金礼包</p>' +
        '<ul class="flex">' +
        '<li><span>10元现金</span><p><time>01:59:59</time>后到期</p></li>' +
        '<li><span>60元现金</span><p><time>01:59:59</time>后到期</p></li>' +
        '</ul>',
        btn: ['立即前往>']
    };

    if (is_app_user) {
        if (!!wx_info && !wx_info.subscribe) {
            option.end = function () {
                layer_open_subscribe();
                $('.layui-m-layer .close').click(function () {
                    layer_open_wxapp();
                    return false;
                });
            };
        } else {
            option.yes = function () {
                location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
            }

            option.end = layer_open_wxapp;
        }
    } else {
        if (!!wx_info && !wx_info.subscribe) {
            option.end = function () {
                layer_open_subscribe();
                $('.layui-m-layer .close').click(function () {
                    show_flush();
                })
            }
        } else {
            option.yes = function () {
                location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
            }

            option.end = function () {
                show_flush();
            }
        }
    }

    layer.open(option);

    $('.layer-download li').click(function () {
        layer.closeAll();
        layer_open_receive($(this).find('span').text());
    });

    var t = $('.layui-m-layer time');
    timesp.push(t.eq(0));
    timesp.push(t.eq(1));
}


function layer_open_award() {
    layer.open({
        className: 'layer-award',
        content:
        '<i class="close"></i>' +
        '<h1>助攻成功率提升10倍</h1>' +
        '<h5>你的好友' + award.nickname + '（新用户）助攻后下载了APP，他帮你助攻的成功率已提升10倍！</h5>'
    });

    $('.layer-award .close').click(function () {
        layer.closeAll();
    })
}

function layer_open_subscribe() {
    layer.open({
        shadeClose: false,
        className: 'layer-subscribe',
        content: '<div class="close-wrap"><i class="close"></i></div>' +
        '<img src="' + wx_info.qrcode + '">' +
        '<p class="tips">长按扫码关注后提现哦</p>' +
        '<button>直接去APP提现（全免费）</button>'
    });

    $('.layui-m-layer button').click(function () {
        location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
    })

    $('.layui-m-layer .close').click(function () {
        layer.closeAll();
    })
}

function layer_open_subscribe_flush() {
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
        show_flush();
    })
}

function layer_open_open() {
    var option = {
        shadeClose: false,
        className: 'layer-receive',
        content: '<div class="close-wrap"><i class="close"></i></div>' +
        '<h1>恭喜你拆到</h1>' +
        '<h2><span class="on">' + open_price + '元</span></h2>' +
        '<h3><time>01:59:59</time>后失效</h3>' +
        '<button>去提现</button>'
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

            if (is_new) option_subscribe.end = show_flush;

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
        if (is_new) option.end = show_flush;
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

function layer_open_receive(txt) {
    if (!txt) txt = '10元现金';

    layer.open({
        className: 'layer-receive',
        shadeClose: false,
        content: '<div class="close-wrap"><i class="close"></i></div>' +
        '<h1>恭喜你领到' + txt + '</h1>' +
        '<h2><time>01:59:59</time>后失效</h2>' +
        '<button>去提现</button>'
    });

    $('.layer-receive button').click(function () {
        if (is_new) {
            layer_open_subscribe();
        } else if (!!wx_info && !wx_info.subscribe) {
            layer_open_subscribe();
        } else {
            layer.closeAll();
            if (is_weixn()) {
                location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
            } else {
                interactive.jumpMine();
            }
        }
    })

    timesp.push($('.layui-m-layer time'));
}

function layer_open_wxapp() {
    layer.open({
        className: 'layer-wxapp',
        shadeClose: false,
        content: '<div class="close-wrap"><i class="close"></i></div>' +
        '<img src="/activity/point_one_buy/img/bg-wxapp-card.png">'
    });
}

function show_flush() {
    $('.help-container').addClass('hide');
    $('.ctn-flush').removeClass('hide');
    $('.wrap').removeClass('help-bg');
}

function hide_flush() {
    $('.help-container').removeClass('hide');
    $('.ctn-flush').addClass('hide');
    $('.wrap').addClass('help-bg');
}

function share_success() {
    if (is_share) return false;

    $.ajax({
        url: '/home/activity/po_buy_share',
        type: 'post',
        data: {id: id},
        success: function (res) {
            $('.btn-buy').removeClass('hide');
            $('.help-container').removeClass('hide');
            $('.btn-share').text('求助好友，提升成功率');
        }
    })
}

function quan_link_pdd(param) {
    if (!param) param = {goods_id: goods_id}
    $.get('/home/goods/quan_link_pdd', param, function (res) {
        if (res.code == 0) {
            if (goods_price == res.data.info.price) {
                if (is_weixn()) {
                    location.href = res.data.info.good_quan_link.url;
                    return false;
                }

                interactive.jumpToPdd(JSON.stringify(res.data.info.good_quan_link));
            } else {
                // 价格不一致 已抢光
                layer.open({content: '晚来一步，商品下架了，请前往兑现吧~', skin: 'msg', time: 3});
            }
        } else {
            if (res.msg) {
                layer.open({content: res.msg, skin: 'msg', time: 3});
            }
        }
    })
}

function join_charge() {
    $.post('/home/activity/po_buy_return_cash', {id: id}, function (res) {
        if (res.code == 0) {
            layer.open({content: res.msg, skin: 'msg', time: 3});

            setTimeout(function () {
                window.location.href = '/home/activity/po_buy_order';
            }, 2000)
        } else {
            if (res.msg) {
                layer.open({content: res.msg, skin: 'msg', time: 3});
            }
        }
    })
}

function pay_ok(res) {
    if (event_key == 'upgrade') {
        location.href = location.href;
        return false;
    }

    if (!link_join) {
        link_join = 'http://' + location.host + '/home/activity/po_buy_goods_buying/';
    }

    // history.pushState({data:'replace_detail'}, "po_index.html", document.referrer);

    if (typeof res === 'object') {
        location.href = link_join + '?id=' + res.data.join_id;
    } else {
        location.href = link_join + '?id=new';
    }
}

function submit_callback(res) {
    if (res.data && res.data.join_id) pay_ok(res);
}

function CountDown() {
    if (maxtime >= 0) {
        var hours = Math.floor(maxtime / 3600 / 1000 % 24);
        var minutes = Math.floor(maxtime / 60 / 1000 % 60);
        var seconds = Math.floor(maxtime / 1000 % 60);
        var ms = Math.floor(maxtime % 1000 / 100);
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
        maxtime = inittime;
    }
}

// 重写支付
function pay_submit(action, data, method, callback) {
    if (is_submit) return false;
    is_submit = true;

    $.ajax({
        url: action,
        data: data,
        type: method,
        success: function (data) {
            if (data.code && data.msg) {
                if (data.msg === '已抢购过该商品') {
                    location.href = link_join + '?id=new';
                } else {
                    if (window["layer"]) layer.open({content: data.msg, skin: 'msg', time: 3});
                    else alert(data.msg);
                }
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

marquee_ul = $('.marquee').find('ul');
if (marquee_ul.length > 0) {
    marquee_step = 0;
    marquee_limit = marquee_ul.find('li')[0].offsetHeight + marquee_step;
    marquee_ul.after(marquee_ul.clone());

    timesp = $('time');
    var inittime = maxtime = 7200000;
    var limit = 1000;
    timer = setInterval("CountDown()", limit);
}