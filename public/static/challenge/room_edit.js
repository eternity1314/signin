$(function () {
    $(".leader-rate right").click(open_leader_rate);

    $(".recommend right").click(function () {
        var _this = this;
        layer.open({
            // title: '温馨提示',
            content: '是否确认推荐族群？',
            className: 'alert',
            btn: ['确定', '取消'],
            yes: function () {
                event_name = 'recommend';
                submit(_this);
            }
        });
    });

    $(".room_fee.invite right").click(function () {
        var i = layer.open({
            type: 0,
            title: '成员越多，赚得越多<i class="close"></i>',
            content: '<p>1、邀请越多好友来自己所创建的族群参与早起打卡挑战，特别是一些<span class="color-primary">新朋友</span>，未加入过早起打卡但想培养早睡早起好习惯的朋友</p>' +
            '<p>2、设置<span class="color-primary">挑战时间越长、约定打卡时间越短</span>，打卡难度就越高，收益也就有可能越高</p>' +
            '<p>3、多利用族群费用给成员发红包，这样大家的活跃度就更高，更能听你的号召多点来班里参与各项挑战和活动，红包可会显示在首页的哦</p>' +
            '<p>4、开通<span class="color-primary">推荐族群</span>服务，可以让你创建的族群展示给更多的人，这样就有可能会有更多的人来你的族群参与挑战，自然收入就可能更高了（服务费¥ 10.00/族/天，以开通时间开始置顶展示24小时）</p>',
            className: 'alert',
            btn: ['推荐族群'],
            yes: function () {
                $(".recommend right").click();
                layer.closeAll();
            }
        });

        $('.close').click(function () {
            layer.close(i)
        });
    })

    $(".room_fee.renew label input").click(function () {
        var _this = this;
        layer.open({
            // title: '温馨提示',
            content: '是否确认续费族群？',
            className: 'alert',
            btn: ['确定', '取消'],
            yes: function () {
                event_name = 'room_fee';
                submit(_this);
            }
        });
    });
    $('.room-upgrade').click(function () {
        var i = layer.open({
            title: '族群续期',
            content: '<div class="room_fee">' +
            '         <i class="close"></i>' +
            '         <ul class="flex ajax-form">\n' +
            '            <label class="flex1">\n' +
            '                <input type="radio" name="room_fee" value="month">\n' +
            '                <i>包月族群</i>\n' +
            '                <button>续费</button>\n' +
            '                <p>￥' + option.room_fee.month + '</p>' +
            '            </label>\n' +
            '            <label class="flex1">\n' +
            '                <input type="radio" name="room_fee" value="year">\n' +
            '                <i>包年族群</i>\n' +
            '                <button>续费</button>\n' +
            '                <p>￥' + option.room_fee.year + '</p>' +
            '            </label>\n' +
            '            <label class="flex1 company">\n' +
            '                <i>企业族群</i>\n' +
            '                <button>待开放</button>\n' +
            '                <p>有实力的来</p>' +
            '            </label>\n' +
            '        </ul>' +
            '</div>',
            className: 'layer-room-fee'
        });

        $('.close').click(function () {
            layer.close(i)
        });

        $(".layer-room-fee label input").click(function () {
            event_name = 'room_fee';
            submit(this);
        })
    })

    $('.room-upgrade-auto label input').change(function () {
        event_name = 'auto';
        $('[name=auto]').val(this.checked ? 1 : 0);
        submit(this);
    })

    $(".edif-info .title").click(function () {
        var e = $(this).find('span');
        if (!e.hasClass('hide')) {
            e.addClass('hide');
            $(this).find('input').removeClass('hide');
        }
    });

    $(".edif-info .title input").change(function () {
        event_name = 'title';
        submit(this);
    })

    $(".layer-pic button").click(function () {
        var e = $(this).closest('.layer-pic');
        if (e.find('[name=pic]').val() == '') {
            // layer.open({content: '请选择图片', skin: 'msg', time: 3});
            e.find('.webuploader-container label').click();
            return false;
        }

        event_name = 'pic';
        submit(this);
    })

    $(".edif-info .pic").click(function () {
        history.pushState({page: 'pic'}, 'pic');

        var p = $('.layer-pic').removeClass('hide');
        var e = p.find('.uploader');
        if (e.hasClass('none')) {
            var bg = p.find('.again').attr('bg');
            if (bg) e.css('background-image', 'url(' + bg + ')').removeClass('none');
        }
    })

    $("body").on("click", ".select-leader-rate li input", function () {
        event_name = 'leader_rate';
        leader_rate = $(this).val();
        submit(this);
        layer.closeAll();
    });

    link_submit = "/home/challenge/room&room_id=" + room_id;
    method_submit = 'PATCH';

    init_uploader('avator');
    init_uploader('pic');

    setTimeout(function () {
        $("[type=file]").removeAttr("capture");
    }, 2000)
})

// layui.use('upload', function () {
//     var upload = layui.upload;
//
//     //执行实例
//     var uploadInst = upload.render({
//         elem: '.edif-info .avator right' //绑定元素
//         , url: '/home/file/picture' //上传接口
//         , accept: 'images'
//         , before: function (obj) {
//             var files = obj.pushFile();
//
//             //预读本地文件，如果是多文件，则会遍历。(不支持ie8/9)
//             obj.preview(function (index, file, result) {
//                 console.log(index); //得到文件索引
//                 console.log(file); //得到文件对象
//                 console.log(result);
//             })
//         }
//         , choose: function () {
//             console.log('sss');
//         }
//         , done: function (res) {
//             //上传完毕回调
//             console.log(arguments);
//         }
//         , error: function () {
//             //请求异常回调
//         }
//     });
// });

function init_uploader(name) {
    if (!window['WebUploader']) {
        $.ajax({
            url: "/static/webuploader/webuploader.min.js",
            async: false,
            success: function () {
                window['uploader_' + name]();
            }
        });
    } else {
        window['uploader_' + name]();
    }
}

function uploader_avator() {
    var up_avator = WebUploader.create({
        auto: true,
        server: '/home/file/picture?_ajax=1',
        pick: '.edif-info .avator right',
        multiple: false,
        accept: {
            title: 'Images',
            extensions: 'gif,jpg,jpeg,bmp,png',
            mimeTypes: 'image/*'
        }
    });

    up_avator.on("fileQueued", function (file) {
        if (file.getStatus() === 'invalid') {
            showError(file.statusText);
        } else {
            var obj = $(".avator i");
            var thumb_size = obj.width();
            up_avator.makeThumb(file, function (error, src) {
                if (error) {
                    layer.open({content: '不能预览', skin: 'msg', time: 3});
                    return;
                }

                obj.css("background-image", "url(" + src + ")");
            }, thumb_size, thumb_size);
        }
    })

    up_avator.on("uploadSuccess", function (file, res) {
        if (res.code) {
            event_name = 'avator';
            var obj = $(".avator i");
            obj.find('[name=avator]').val(res.data.path);
            submit(obj[0]);
        }
    })
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

function open_leader_rate() {
    var content = '';
    var rate = option.leader_rate;
    for (var i in rate) {
        content += '<li><label><input type="radio" name="leader_rate" value="' + rate[i] + '">' + rate[i] + '%</label></li>';
    }

    layer.open({
        type: 1,
        content: '<ul class="ajax-form"><li>请选择设置费用比例</li>' + content + '</ul>'
        , anim: false
        , className: 'list select-leader-rate'
    });
}

function pay_ok() {
    switch (event_name) {
        case 'leader_rate':
            $(".leader-rate span").text(leader_rate);
            layer.open({content: '修改成功', skin: 'msg', time: 3});
            break;
        case 'recommend':
            layer.open({content: '推荐成功，将会显示在首页等位置', skin: 'msg', time: 3});
            break;
        case 'room_fee':
            layer.open({
                content: '续费成功', skin: 'msg', time: 3, end: function () {
                    location.href = location.href;
                }
            });
            break;
        case 'title':
            var e = $(".edif-info .title");
            e.find("span").text(e.find('input').addClass('hide').val()).removeClass('hide');
            layer.open({content: '修改成功', skin: 'msg', time: 3});
            break;
        case 'avator':
            layer.open({content: '上传成功', skin: 'msg', time: 3});
            break;
        case 'pic':
            var e = $('.layer-pic').addClass('hide');
            e.find('.uploader').css('background-image', e.find('[name=pic]').val()).removeClass('none');
            layer.open({content: '上传成功', skin: 'msg', time: 3});
            break;
        case 'auto':
            console.log($('[name=auto]').val());
            layer.open({content: parseInt($('[name=auto]').val()) ? '已开启' : '已关闭', skin: 'msg', time: 3});
            break;
        default :
            break;
    }
}

function submit_callback(data) {
    if (data.data && data.data.update_id) pay_ok();
}

window.onpopstate = function (e) {
    $('.layer-pic').addClass('hide');
}