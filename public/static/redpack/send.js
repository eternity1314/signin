$(function () {
    /*输入数字*/
    $("[name=num]").on("keyup", function () {
        var $this = $(this);
        var val = $this.val();
        if (/[^\d]/g.test(val)) {
            $this.val(val.replace(/[^\d]/g, ''));
        }
        val = parseFloat(val);
        if (val < 1) {
            // 禁止小于1
            $(this).val(1);
        }
    });

    $("[name=price]").on("keyup", function () {
        var price = $(this).val();

        if (send_event == "integral") {

            var $this = $(this);
            var val = $this.val();
            if (/[^\d]/g.test(val)) {
                $this.val(val.replace(/[^\d]/g, ''));
            }
            val = parseFloat(val);
            if (val < 1) {
                // 禁止小于1
                $(this).val(1);
            }
        } else {
            var reg = /^[0-9]+(\.[0-9]{0,2})?$/;
            if (!reg.test(price)) {
                // 特殊字符 或  小数点数量大于1 取消填入
                if (isNaN(price) || (price.toString().split('.')).length > 2) {
                    price = price.substring(0, price.length - 1);
                }
                // 保留2位小数
                if (price.toString().split(".")[1] && price.toString().split(".")[1].length > 2) {
                    price = parseFloat(price);
                    price = Math.floor(price * 100) / 100;
                }

                $(this).val(price);

            } else {
                // price = parseFloat(price);
                price = price;
                if (price == '00') {
                    // 禁止小于1
                    $(this).val(1);
                } else if (price == 0.01 || price == 0.02 || price == 0.03 || price == 0.04 || price == 0.05 || price == 0.06 || price == 0.07 || price == 0.08 || price == 0.09) {
                    $(this).val(1);
                } else if (isNaN(price)) {
                    // 禁止  1. 的情况
                    $(this).val(1);
                }
            }
        }

        calc_total();

    });

    $("[name=event]").val(send_event);

    $(".cevent").click(function () {
        if (send_event == "mb") {
            $(".paid").text(0);
            // var balance_name = "金额";
            // var unit = "元";
            // var current_name = "人民币";
            // var change_name = "积分";
            send_event = "rmb";
        } else {
            // var balance_name = "积分";
            // var unit = "分";
            // var current_name = "积分";
            // var change_name = "人民币";
            send_event = "mb";
            $(".paid").text(0.00);
        }

        // var input = $(".input:eq(0)")
        // var span = input.find("span");
        // span.eq(0).text(balance_name);
        // span.eq(1).text(unit);
        // span = $(this).closest(".tips").find("span");
        // span.eq(0).text(current_name);
        // span.eq(1).text(change_name);

        $(".input:eq(0)").find("input").val("");
        $(".input:eq(1)").find("input").val("");
        $("[name=event]").val(send_event);
        // $(".pay").find("[type=checkbox]").prop("disabled", true);
        $(".pay." + send_event).find("[type=checkbox]").prop("disabled", false);
        $(".send-box").removeClass().addClass("send-box " + send_event);
        $("[name=price]").attr("placeholder", $("[name=price]").attr(send_event + "-placeholder"));
        $(".submit").prop("disabled", true);
    })

    $("[name=pay_rmb]").on("change", function () {
        calc_total();
    })

    $("[name=pay_mb]").click(function () {
        calc_total();
    })

    $("[name=num]").on("keyup", function () {
        var obj = $(".submit");
        var val = parseFloat($("[name=price]").val());

        if (send_event == 'integral') {
            if (isNaN(val) || val < 100) {
                obj.prop("disabled", true);
                return false;
            }
        } else {
            if (isNaN(val) || val <= 0.1) {
                obj.prop("disabled", true);
                return false;
            }
        }

        val = parseFloat($(this).val());
        if (isNaN(val) || val <= 0) {
            obj.prop("disabled", true);
            return false;
        }

        obj.prop("disabled", false);
        return true;
    }).on("keyup", function () {
        var obj = $(".submit");
        var val = parseFloat($("[name=price]").val());

        if (send_event == 'mb') {
            if (isNaN(val) || val < 100) {
                obj.prop("disabled", true);
                return false;
            }
        } else {
            if (isNaN(val) || val < 0.1) {
                obj.prop("disabled", true);
                return false;
            }
        }

        val = parseFloat($(this).val());
        if (isNaN(val) || val <= 0) {
            obj.prop("disabled", true);
            return false;
        }

        obj.prop("disabled", false);
        return true;
    })

    $('.label-set').click(function () {
        $('.send-box').addClass('hide');
        $('.ctn-set').removeClass('hide');

        history.pushState({page: 'set'}, 'set');
    })

    $('.ctn-set header .point').click(function () {
        $('.ctn-set').addClass('hide');
        $('.send-box').removeClass('hide');
    })

    $('.ctn-set ul li.show').click(function () {
        layer.open({
            type: 1,
            content: '<ul class="show">' +
            '<dd>红包显示在哪里</dd>' +
            '<li value="1">显示在本族群</li>' +
            '<li value="0" text="不显示在本族群">不显示在本族群<i>如非微信打开需关注公众号</i></li>' +
            '</ul>'
            , anim: false
            , className: 'select'
        });
    })

    $('.ctn-set ul li.receive').click(function () {
        layer.open({
            type: 1,
            content: '<ul class="receive">' +
            '<dd>谁可以领我的红包</dd>' +
            '<li value="room">本族成员领取</li>' +
            // '<li value="pinduoduo">拼多多用户领取</li>' +
            '<li value="all">看到的人就能领</li>' +
            '</ul>'
            , anim: false
            , className: 'select'
        });
    })

    $('.ctn-set ul li.share').click(function () {
        layer.open({
            type: 1,
            content: '<ul class="share">' +
            '<dd>怎么才能领取到红包</dd>' +
            '<li value="1">分享后才能领取</li>' +
            '<li value="0">无需分享就能领</li>' +
            '</ul>'
            , anim: false
            , className: 'select'
        });
    })

    $('.ctn-set ul li.transfer').click(function () {
        layer.open({
            type: 1,
            content: '<ul class="transfer">' +
            '<dd>红包显示在哪里</dd>' +
            '<li value="account">微选钱包</li>' +
            '<li value="wx" text="微信钱包">微信钱包<i>前提需关注公众号</i></li>' +
            '</ul>'
            , anim: false
            , className: 'select',
        });
    })

    $('.ctn-set ul li.pic').click(function () {
        $('.ctn-set').addClass('hide');
        $('.ctn-pic').removeClass('hide');
        init_uploader();

        history.pushState({page: 'pic'}, 'pic');
    })

    $('body').on('click', '.layui-m-layer .select li', function () {
        var p = $(this).closest('ul').attr('class');
        $('[name=' + p + ']').val($(this).attr('value'));
        var t = $(this).attr('text') || $(this).text();
        $('.ctn-set li.' + p + ' i').text(t);
        layer.closeAll();
    })

    $('.ctn-pic header .point').click(function () {
        $('.ctn-pic').addClass('hide');
        $('.ctn-set').removeClass('hide');
    })

    $(".layer-pic button").click(function () {
        var e = $(this).closest('.layer-pic');
        if (e.find('[name=pic]').val() == '') {
            // layer.open({content: '请选择图片', skin: 'msg', time: 3});
            e.find('.webuploader-container label').click();
            return false;
        }

        // $('.ctn-pic header .point').click();
        history.go(-1);
    })
})

function calc_total() {
    var balance = parseFloat($("[name=price]").val());

    if (isNaN(balance)) balance = 0;

    if (send_event == "integral") {
        var val = balance;
    } else {
        var val = balance.toFixed(2);
    }

    $(".paid").text(val);
}

if (location.href.indexOf("mb") > 0) {
    send_event = "mb";
} else {
    send_event = "rmb";
}

function check() {
    var obj = $(".submit");
    var val = parseFloat($("[name=price]").val());
    if (isNaN(val) || val <= 0) {
        obj.prop("disabled", ture);
        return false;
    }

    val = parseFloat($("[name=num]").val());
    if (isNaN(val) || val <= 0) {
        obj.prop("disabled", ture);
        return false;
    }

    obj.prop("disabled", false);
    return true;
}

function pay_ok() {
    location.href = link_pay_ok;
}

function submit_callback(res) {
    if (res.data && res.data.redpack_id) pay_ok();
}

function init_uploader() {
    var name = 'pic';
    if (!window['WebUploader']) {
        $.ajax({
            url: "/static/webuploader/webuploader.min.js",
            async: false,
            success: function () {
                window['uploader_' + name]();
            }
        });
    }
}

function uploader_pic() {
    var up_pic = WebUploader.create({
        auto: true,
        server: '/home/file/picture?_ajax=1',
        pick: '.uploader',
        multiple: false,
        accept: {
            title: 'Images',
            extensions: 'gif,jpg,jpeg,bmp,png',
            mimeTypes: 'image/*'
        }
    });

    up_pic.on("fileQueued", function (file) {
        if (file.getStatus() === 'invalid') {
            showError(file.statusText);
        } else {
            var obj = $(".layer-pic .uploader");
            var thumb_size = obj.width();
            up_pic.makeThumb(file, function (error, src) {
                if (error) {
                    layer.open({content: '不能预览', skin: 'msg', time: 3});
                    return;
                }

                obj.css("background-image", "url(" + src + ")").removeClass("none");
            }, thumb_size, thumb_size);
        }
    })

    up_pic.on("uploadSuccess", function (file, res) {
        if (res.code) {
            $('.layer-pic [name=pic]').val(res.data.path);
        }
    })
}

function showError(code) {
    switch (code) {
        case 'exceed_size':
            var text = '文件大小超出';
            break;

        case 'interrupt':
            var text = '上传暂停';
            break;

        default:
            var text = '上传失败，请重试';
            break;
    }

    layer.open({content: text, skin: 'msg', time: 3});
}


window.onpopstate = function (e) {
    if (e.state && e.state.page) {
        if (e.state.page == 'set') {
            $('.ctn-pic').addClass('hide');
            $('.ctn-set').removeClass('hide');
        }
    } else {
        $('.ctn-set').addClass('hide');
        $('.send-box').removeClass('hide');
    }
}