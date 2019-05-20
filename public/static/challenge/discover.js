$(function () {
    $('.btn-pic').click(function () {
        var val = $(this).attr('val');
        if (!val) {
            layer.open({content: '族长很懒，还没上传二维码', skin: 'msg', time: 3});
            return false;
        }

        history.pushState({page: 'pic'}, 'pic');

        var obj = $('title');
        var title = obj.text();
        obj.text(obj.attr('val')).attr('val', title);

        obj = $('.ctn-page').addClass('hide').filter('.pic').removeClass('hide');
        if (obj.find('img').length == 0) obj.prepend('<img src="' + val + '">');
    })

    $('.btn-wechat').click(function () {
        var val = $(this).attr('val');
        if (!val) {
            layer.open({content: '族长很懒，还没上传微信号', skin: 'msg', time: 3});
            return false;
        }

        layer.open({
            title: '族长微信号或手机号码',
            content: '<i class="close"></i>' +
            '<p>添加对方为微信好友</p>' +
            '<input type="text" readonly value="' + val + '">' +
            '<button class="btn-copy">点击复制</button>'
            , className: 'layer-copy'
        });

        $('.layer-copy input').click(function () {
            this.select();
        })

        $('.layer-copy .close').click(function () {
            layer.closeAll();
        })

        if (window['interactive']) {
            $('.layer-copy .btn-copy').click(function () {
                interactive.copy(val);
            })
        }
    })

    $('.btn-invite').click(function () {
        var val = $(this).attr('val');
        layer.open({
            title: '族长邀请码',
            content: '<i class="close"></i>' +
            '<input type="text" readonly value="' + val + '">' +
            '<button class="btn-copy">点击复制</button>'
            , className: 'layer-copy'
        });

        $('.layer-copy input').click(function () {
            this.select();
        })

        $('.layer-copy .close').click(function () {
            layer.closeAll();
        })

        if (window['interactive']) {
            $('.layer-copy .btn-copy').click(function () {
                interactive.copy(val);
            })
        }
    })
})

next_id = 0;
layui.use('flow', function () {
    layui.flow.load({
        elem: '.read-more',
        done: function (page, next) {
            $.get(link_load, {next_id: next_id}, function (res) {
                next_id = res.data.next_id;
                var html = [];
                layui.each(res.data.article_data, function (i, v) {
                    html.push('<li>' +
                        '<a class="list-item list-item1" href="' + link_article + '?id=' + v.id + '">' +
                        '<h3 class="a_t">' + v.title + '</h3>' +
                        '<div class="a_p"><img src="' + v.info.img_list[0] + '"></div>' +
                        '<div class="a_m">' + v.author.nickname + "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" + '阅读' + v.read_num + '</div>' +
                        '</a>' +
                        '</li>');
                })

                next('', next_id > 0);
                if (html.length > 0) $('.article-list ul').append(html);
            })
        }
    })
})

window.onpopstate = function (e) {
    if (e.state && e.state.page) {

    } else {
        $('.ctn-page').addClass('hide').filter('.normal').removeClass('hide');
    }
}