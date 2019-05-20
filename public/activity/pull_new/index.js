$(function () {
    if (!is_weixn()) {
        $.ajax({
            url: "/static/js/interactive.js",
            success: function () {
                $('.btn-withdraw').click(function () {
                    interactive.jumpWithdrawPage();
                });
                $('.btn-share').click(function () {
                    interactive.btn_click();
                });
            }
        });
    } else {
        $('.btn-withdraw').click(function () {
            layer.open({
                title: '温馨提醒',
                content: '亲，为了您的账户安全和利于体验，微信H5版本暂时不支持该功能，请下载APP进行操作，将会给您带来更多额外的体验和收益',
                btn: '好的',
                className: 'alert',
                shadeClose: false,
                yes: function () {
                    location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
                }
            });
        });

        $('.btn-share').click(open_share);
    }

    $.ajax({
        url: '/home/activity/pull_new' + location.search, success: function (res) {
            if (!res.data.partner_time) {
                $('body').prepend('<a class="link-equities" href="/home/goods/user_equities"></a>')
            }

            if (res.data.this_week) {
                this_week = res.data.this_week;
                if (this_week.length > 0) {
                    var html = [];
                    var lis = [];
                    var sec = $('<section class="flex"></section>');
                    html.push(sec);
                    for (var i in res.data.this_week) {
                        var v = res.data.this_week[i];
                        lis.push('<li><span><em style="background-image: url(' + v.avator + ')"></em>' + v.nickname + '领了' + v.award + '元</span></li>')

                        if (i < 3) {
                            sec.append('<div class="flex1">' +
                                '<em style="background-image: url(' + v.avator + ')"></em>' +
                                '<i>' + v.nickname + '</i>' +
                                '<p>' + v.award + '元</p>' +
                                '</div>')
                        } else if (i < 6) {
                            html.push($('<li class="flex" no="' + (parseInt(i) + 1) + '">' +
                                '<em style="background-image: url(' + v.avator + ')"></em>' +
                                '<div class="txt-ellipsis">' + v.nickname + '</div>' +
                                '<i class="flex1">' + v.award + '元</i>' +
                                '</li>'))
                        }
                    }

                    $('.ctn.income .list[event=this]').prepend(html);
                    $('.marquee ul').append(lis);

                    var marquee = $('.marquee');
                    var marquee_ul = marquee.find('ul')
                    var marquee_li = marquee.find('li');
                    var marquee_li_len = marquee_li.length;
                    marquee.find('ul').append(marquee_li.clone());
                    marquee_li.eq(0).css('opacity', 1);
                    marquee_li = marquee.find('li');
                    li_height = marquee_li[0].offsetHeight + 10;
                    li_limit = 0;
                    setInterval(function () {
                        marquee_ul.animate({'top': (0 - (++li_limit * li_height)) + 'px'}, function () {
                            if (li_limit >= marquee_li_len) {
                                marquee_ul.css({'top': 0});
                                marquee_li.eq(0).css({'opacity': 1});
                                marquee_li.eq(marquee_li_len).stop().css({'opacity': 0});
                                li_limit = 0;
                            }
                        });

                        marquee_li.eq(li_limit - 1).animate({'opacity': 0});
                        marquee_li.eq(li_limit).animate({'opacity': 1});
                    }, 3000);
                } else {
                    $('.ctn.income .list[event=this]').prepend('<div class="empty">暂无内容</div>');
                }
            }

            if (res.data.last_week) {
                last_week = res.data.last_week;
                if (last_week.length > 0) {
                    var html = [];
                    var sec = $('<section class="flex"></section>');
                    html.push(sec);
                    for (var i in res.data.last_week) {
                        var v = res.data.last_week[i];
                        if (i < 3) {
                            sec.append('<div class="flex1">' +
                                '<em style="background-image: url(' + v.avator + ')"></em>' +
                                '<i>' + v.nickname + '</i>' +
                                '<p>' + v.award + '元</p>' +
                                '</div>')
                        } else {
                            html.push($('<li class="flex" no="' + (parseInt(i) + 1) + '">' +
                                '<em style="background-image: url(' + v.avator + ')"></em>' +
                                '<div class="txt-ellipsis">' + v.nickname + '</div>' +
                                '<i class="flex1">' + v.award + '元</i>' +
                                '</li>'))
                        }

                        if (i >= 5) break;
                    }

                    $('.ctn.income .list[event=last]').prepend(html);
                } else {
                    $('.ctn.income .list[event=last]').prepend('<div class="empty">暂无内容</div>');
                }
            }

            if (res.data.my_award) {
                var v = res.data.my_award;
                var p = $('.ctn-rank');
                p.find('.this_week_rank').text(v.this_week_rank);
                p.find('.invite_count').text(v.invite_count);
                p.find('.week_invite_award').text(v.week_invite_award);
                p.find('.all_invite_award').text(v.all_invite_award);
            }

            if (res.data.link_poster) {
                $('.btn-poster').click(function () {
                    layer.open({
                        type: 1,
                        content: '<i class="close"></i><img src="' + res.data.link_poster + '">',
                        className: 'layer-poster'
                    });

                    $('.layer-poster .close').click(function () {
                        layer.closeAll();
                    })

                    interactive.share(res.data.link_poster);
                })
            }

            if (res.data.wx_share) {
                wx_share = res.data.wx_share;
                share_load();
            }

            if (res.data.wx_config) wx_config = res.data.wx_config;
        }
    });

    $('.ctn.income dt').click(function () {
        var $this = $(this);
        if ($this.hasClass('on')) return false;
        var i = $this.index();
        var p = $this.closest('.income');
        p.find('dt').removeClass('on').eq(i).addClass('on');
        p.find('.list').addClass('hide').eq(i).removeClass('hide');
    })

    $('.ctn.income button').click(function () {
        var e = $(this).closest('.list').attr('event');
        console.log(e);
        var html = [];
        for (var i in window[e + '_week']) {
            var v = window[e + '_week'][i];
            html.push('<li class="flex" no="' + (parseInt(i) + 1) + '">' +
                '<em style="background-image: url(' + v.avator + ')"></em>' +
                '<div class="txt-ellipsis">' + v.nickname + '</div>' +
                '<i class="flex1">' + v.award + '元</i>' +
                '</li>');
        }

        layer.open({
            content: '<i class="shut"></i>' +
            '<ul class="income">' +
            html.join('') +
            '</ul>',
            className: 'layer-rank'
        });

        $('.layer-rank .shut').click(function () {
            layer.closeAll();
        })
    })

    $('.btn-desc').click(function () {
        layer.open({
            title: '补贴说明',
            content: '<p>1.活动时间：10月29日~11月29日</p>' +
            '<p>2.拉新补贴仅限合伙人领取，超级会员可升级合伙人后再邀请好友领取补贴。</p>' +
            '<p>3.活动时间内，只要您的邀请的直属用户在微选生活首次购买任意商品，你可获得10元拉新补贴。</p>' +
            '<p>4.直属成员使用免单0元购，你也能领取补贴哦~</p>' +
            '<p>5.10元拉新补贴=5元现金补贴+5元免单补贴；每月结算日到账后，可随时提现。</p>' +
            '<p>6.每周一11:00，排行榜对上周数据进行结算，排行前20名每人再获得300元现金补贴。</p>' +
            '<p>本活动最终解释权归微选生活所有</p>',
            btn: ['好的'],
            className: 'alert'
        });
    })
})