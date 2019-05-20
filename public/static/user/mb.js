layui.use('flow', function () {
    var flow = layui.flow;
    flow.load({
        elem: '.list',
        done: function (page, next) {
            $.get(location.href, function (res) {
                var html = [];
                layui.each(res.data.list, function (index, item) {
                    var li = '<li>' +
                        '<p class="flex"><span class="flex1">' + item.event_name + '</span>' + (item.mb > 0 ? '+' : '') + item.mb + '</p>' +
                        '<p class="flex"><span class="flex1"></span>' + item.time + '</p>\n' +
                        '</li>';

                    html.push(li);
                });

                next(html.join(''), false);
                $('.layui-flow-more').text('没有M币记录哦');
            });
        }
    });
});

$(function () {
    if(is_weixn()){
        $('.make-more').click(function () {
            layer.open({
                title: '温馨提醒',
                content: '亲，为了您的账户安全和利于体验，微信H5版本暂时不支持该功能，请下载APP进行操作，将会给您带来更多额外的体验和收益',
                btn: '好的',
                className: 'alert'
            });
            return false;
        })
    }
})