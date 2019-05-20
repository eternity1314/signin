$(function () {
    $('.cycle li').each(function () {
        var deg = Math.floor(Math.random() * 360);
        $(this).css({'transform': 'rotate(' + deg + 'deg)'}).find('i').css({
            'transform': 'rotate(-' + deg + 'deg)',
            'margin-top': Math.floor(Math.random() * 36) + 'vw'
        });//.append('<em style="background-image: url(/static/img/head.png)"></em>');
    }).find('i').click(function () {
        open_redpack_draw();
        var $this = $(this).closest('li');
        event_index = $this.index();
        var obj = $(".draw");
        //obj.find(".info").attr("href", link_redpack_info + "&redpack_id=" + event_id);
        obj.find("em").css("background-image", $this.find("em").css("background-image"));
        obj.find(".nickname").text($this.attr("nickname"));
        if ($this.attr("errmsg")) {
            var msg = $this.attr("errmsg");
            obj.find("button").addClass("hide");
        } else {
            var msg = $this.attr("msg");
            obj.find("button").removeClass("hide");
        }
        obj.find(".msg").text(msg);
        obj.addClass("visible");

        if ($this.attr('receive') == "pinduoduo") isInstallPinduoduo();
    })

    if (!is_weixn()) {
        $.ajax({url: "/static/js/interactive.js"});
    }

    if (window["wx_share"]) {
        wx_share.success = function () {
            if ($(".draw").length > 0) {
                $(".draw button").click();
            } else if ($(".prize").length > 0) {
                $(".prize .pbottom button").click();
            }

            layer.close($(".layui-m-layer-share").attr('index'));
        }
    }

    var redpack_id = getUrlParam('redpack_id');
    if (redpack_id) {
        $('.cycle li[rid=' + redpack_id + '] i').click();
    }
})

function open_redpack_draw() {
    layer.open({
        type: 1,
        content: `<section class="draw">
        <i class="close"></i>
        <em style="background-image: url(/static/img/head.png)"></em>
        <div class="nickname"></div>
        <div class="redpacket_text">发一个红包，金额随机</div>
        <div class="msg"></div>
        <a class="info">@微选生活提供技术支持</a>
        <button><dt></dt></button>
        </section>`,
        anim: false,
        className: 'layer-draw',
        shadeClose: false
    })

    var e = $('.draw');
    e.find('.close').click(function () {
        // location.href = location.href;
        layer.closeAll();
    })

    e.find('button').click(redpack_open);
}

function redpack_open() {
    var $this = $(this);
    var obj = $(".cycle li").eq(event_index);

    var data = {redpack_id: obj.attr('rid')};
    var assign_id = $this.attr("assign_id");
    if (assign_id) {
        var receive = obj.attr("receive");
        if (receive == "room") {
            if (!is_join) {
                layer.open({
                    content: '<h5>抱歉</h5>' +
                    '<p>族员才能领取该红包<br>请先加入族群</p>' +
                    //'<a href="' + link_join + '">好的，现在加入</a>' +
                    '<a href="' + link_join + '" style="background-image: url(/static/challenge/img/bg-room.png)"></a>' +
                    '<support>@微选生活提供技术支持</support>',
                    anim: false,
                    className: 'draw-panel room'
                });
                return false;
            }
        } else if (receive == "pinduoduo") {
            if (!isInstallPinduoduo()) {
                layer.open({
                    content: '<h5>抱歉</h5>' +
                    '<p>安装有拼多多APP的人才能领取该红包</p>' +
                    //'<a>非常遗憾<br>您不是拼多多的用户</a>' +
                    '<a style="background-image: url(/static/challenge/img/bg-not-pinduoduo.png)"></a>' +
                    '<support>@微选生活提供技术支持</support>',
                    anim: false,
                    className: 'draw-panel pinduoduo'
                });
                return false;
            }
        }

        var share = obj.attr("share") == "1";//.is("[share]");
        if (share) {
            share = $(".layui-m-layer-share").length == 0;
            if (share) {
                var i = layer.open({
                    content: '<h5>抱歉</h5>' +
                    '<p>发红包的人设置分享后才能领取该红包，点击下方分享</p>' +
                    '<a style="background-image: url(/static/challenge/img/bg-pengyouquan.png)"></a>' +
                    '<support>@微选生活提供技术支持</support>',
                    anim: false,
                    className: 'draw-panel'
                });

                $('.draw-panel').click(function () {
                    layer.close(i);
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
                })

                return false;
            }
        }

        data.assign_id = assign_id;
    } else {
        $this.addClass("animation");
    }

    $this.prop("disabled", true);
    var date = new Date();
    $.ajax({
        url: link_redpack_draw,
        type: "post",
        data: data,
        success: function (data) {
            if (data.code && data.msg) {
                layer.closeAll();
                layer.open({content: data.msg, skin: 'msg', time: 3});
                return;
            }

            if (assign_id) {
                $(".cycle li").eq(event_index).remove();
                layer.closeAll();
                layer.open({
                    content: '<h5><i style="background-image: url(' + data.data.icon + ')"></i>' + (data.code ? '<em>抱歉</em>' : '') + '</h5>' +
                    '<p>' + data.data.msg + '</p>' +
                    '<a style="background-image: url(' + data.data.pic + ')" ' + (data.data.link ? 'href="' + data.data.link + '"' : '') + ' >' +
                    (data.code == 105 ? '<img src="' + data.data.pic + '">' : '') +
                    '</a>' +
                    '<support>@微选生活提供技术支持</support>',
                    anim: false,
                    className: 'draw-panel'
                });

                // $this.prop("disabled", false);
                // var ad = $(".prize .ptop a");
                // var link = ad.attr("href");
                // if (link && parseInt($this.attr("forward")) == 1) {
                //     history_state = {"go": $(".draw .info").attr("href")};
                //     history.pushState({}, "");
                //     sessionStorage.setItem("history_state", JSON.stringify(history_state));
                //     location.href = link;
                // } else {
                //     $(".draw .info").get(0).click();
                // }
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
    var obj = $(".draw").find("button");

    if (data.data && data.data.txt) {
        obj.removeClass("animation").html(data.data.txt);
    } else {
        obj.removeClass("animation").prop("disabled", false).attr('assign_id', data.data.assign_id)
            .html("恭喜您<p class='txt-" + data.data.event + "'>" + (data.data.event == 'rmb' ? data.data.price / 100 : data.data.price) + "</p><i>领取</i>");
    }
}

function isInstallPinduoduo() {
    if (window['is_pinduoduo']) return is_pinduoduo;

    try {
        window.qushenghuo.isInstallPinduoduo()
    } catch (error) {
        console.log(error)
    }

    try {
        window.webkit.messageHandlers.isInstallPinduoduo.postMessage(null)
    } catch (error) {
        console.log(error)
    }

    return false;
}

function isInstallPinduoduoResult(res) {
    if (res) is_pinduoduo = true;
}

function getUrlParam(name) {
    var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)");
    var r = window.location.search.substr(1).match(reg);
    if (r != null) return unescape(r[2]);
    return null;
}