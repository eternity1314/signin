$(function () {
    if (is_weixn()) {
        function download() {
            layer.open({
                title: '温馨提醒',
                content: '亲，为了您的账户安全和利于体验，微信H5版本暂时不支持该功能，请下载APP进行操作，将会给您带来更多额外的体验和收益',
                btn: '好的',
                className: 'alert',
                shadeClose: false,
                yes: function () {
                    location.href = 'http://a.app.qq.com/o/simple.jsp?pkgname=com.mayixiguan'
                }
            })
        }

        $('.ctn-income li').click(download);

        $('.btn-store').click(function () {
            if ($(this).attr('level') >= 3) {
                layer.open({content: '店铺正在装修中...<br><br>很快将会跟大家见面啦', skin: 'msg', time: 3})
            } else {
                download()
            }
        })
    } else {
        $.ajax({"url": "/static/js/interactive.js"});
        $('.ctn-income .all-income').click(function () {
            interactive.jumpAllIncomePage();
        })
        $('.ctn-income .balance').click(function () {
            interactive.jumpWithdrawPage();
        })
        $('.ctn-income .withdraw').click(function () {
            interactive.jumpAlreadyWithdrawPage();
        })
        $('.btn-store').click(function () {
            if ($(this).attr('level') < 3) {
                layer.open({
                    title: 'VIP会员可拥有个人店铺',
                    content: '0囤货、0发货，快速拥有拼多多/京东店铺',
                    btn: ['去升级'],
                    className: 'alert',
                    shadeClose: false,
                    yes: function () {
                        interactive.jumpFreeOrderPage();
                    }
                })
            } else {
                interactive.jumpFreeOrderPage();
            }
        })
    }
})