next_id_ing = -1;
next_id_over = -1;
next_id_create = -1;

$(function () {
    if (is_weixn()) {
        $('.user .balance,.user .promotion').click(function () {
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
            return false;
        })
    } else {
        $('.user .balance').click(function () {
            try {
                if (window.webkit) window.webkit.messageHandlers.jumpMine.postMessage(null)
                window.qushenghuo.jumpMine();
            } catch (e) {
            }
            return false;
        })

        $('.user .promotion').click(function () {
            try {
                if (window.webkit) window.webkit.messageHandlers.goto_goods_link.postMessage(null)
                window.qushenghuo.goto_goods_link();
            } catch (e) {
            }
            return false;
        })

        $.ajax({url: "/static/js/interactive.js"});
    }

    $(".tab li").click(function () {
        var i = $(this).index();
        $(".tab li").removeClass("on").eq(i).addClass("on");
        $('.more .layui-flow-more.show').remove();
        var e = $(".list").removeClass("show").eq(i).addClass("show");
        if (e.find('li').length == 0) {
            $(".more a").click();
        }
    })

    $("ol").on("click", "li .info", function () {
        var room_id = $(this).closest('li').attr('room_id');
        location.href = '/home/challenge/join&room_id=' + room_id;
    })

    $("#create").on("click", ".info .status right", function () {
        var room_id = $(this).closest('li').attr('room_id');
        location.href = '/home/challenge/room&room_id=' + room_id;
        return false;
    })

    $("#ing").on("change", "[type=checkbox]", function () {
        var $this = $(this);
        //console.log(this.checked, $this.prop('checked'), $this);
        pay_submit('/home/challenge/join_auto_change', {
            challenge_id: $this.closest('li').attr('challenge_id'),
            auto: this.checked ? 1 : 0
        }, 'POST');
    })

    if (typeof room_id != 'undefined' && room_id == 0) {
        $('.bottom_bar .off[href]').click(function () {
            layer.open({content: '你还没参与过任何族群，请返回首页参与', skin: 'msg', time: 3});
            return false;
        })
    }

    layui.use('flow', function () {
        layui.flow.load({
            elem: '.more',
            done: function (page, next) {
                var event = $('.list:visible').attr('id');
                var next_id = window['next_id_' + event];
                if (next_id == 0) {
                    next('', true);
                    if ($('.more .layui-flow-more.show').length == 0) {
                        $('.more').prepend('<div class="layui-flow-more show">没有更多了</div>');
                    }
                    return false;
                }

                var data = {event: event};
                if (next_id > 0) data.next_id = next_id;

                $.get(location.href, data, function (res) {
                    window['next_id_' + event] = res.data.next_id;
                    var html = [];
                    layui.each(res.data.list, function (index, item) {
                        var e = $(`<li>
                            <div class="info flex">
                                <em></em>
                                <div class="detail flex1">
                                    <p class="title"><span></span><right></right></p>
                                    <p class="stime"></p>
                                    <p class="status">
                                        <span></span>
                                        <right></right>
                                    </p>
                                </div>
                            </div>
                        </li>`).attr('room_id', item.room_id).attr('challenge_id', item.challenge_id);
                        html.push(e);

                        if (event == 'ing') {
                            var room = res.data.room[item.room_id];
                            e.find('.info em').css('background-image', 'url(' + room.avator + ')')
                            var t = e.find('.title');
                            t.find('span').text(room.title).after('（总赚￥' + room.income + '）');
                            t.find('right').text('连续' + item.day + '天');
                            e.find('.info .detail .stime').text(item.stime);
                            t = e.find('.info .detail .status').addClass('s s' + item.state);
                            t.find('span').text(item.status);
                            t.find('right').text(item.remark);

                            t = $(`<div class="remark">
                                契约金：￥<span>0</span>
                                <right>
                                    挑战成功后继续参与
                                    <label class="label-switch"><input type="checkbox"><i class="checkbox"></i></label>
                                </right>
                            </div>`).appendTo(e);
                            t.find('span').text(item.price);
                            if (item.auto == '1') t.find('[type=checkbox]').prop('checked', true);
                        } else if (event == 'over') {
                            var room = res.data.room[item.room_id];
                            e.find('.info em').css('background-image', 'url(' + room.avator + ')')
                            var t = e.find('.title');
                            t.find('span').text(room.title).after('（总赚￥' + room.income + '）');
                            t.find('right').text('连续' + item.day + '天');
                            e.find('.info .detail .stime').text(item.stime);
                            e.find('.info .detail .status span').text(item.status);
                        } else if (event == 'create') {
                            e.find('.info em').css('background-image', 'url(' + item.avator + ')')
                            var t = e.find('.title');
                            t.find('span').text(item.title);
                            t.find('right').text('连续' + item.day + '天');
                            e.find('.info .detail .stime').text('约定打卡时间' + item.stime);
                            e.find('.info .detail .status span').text('历史收益率' + item.income_rate.toFixed(3) + '%');
                        }
                    });

                    if (html.length > 0) $('#' + event).append(html);
                    next('', next_id_ing != 0 || next_id_over != 0 || next_id_create != 0);
                    if (res.data.next_id == 0) {
                        $('.more').prepend('<div class="layui-flow-more show">没有更多了</div>');
                    }
                });
            }
        });
    });
})
