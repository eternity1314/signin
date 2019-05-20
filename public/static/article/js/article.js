$(function(){
	// 详情 -> n秒后

	// 
	$('.icon-rule-txt').click(function(){
        $('.overlay').addClass('visible');
        $('.article-pannel').addClass('visible');
    })

    // url链接
    $('#web-link').click(function(){
    	var url = $(this).data('url');console.log(url);
    	window.location.host = url;
    })

    // 关闭弹出层
    $(document).on('click', '.layui-m-layershade', function(){
    	$('.share-modal').hide().prev('.layui-m-layershade').remove();
    })

    // 详情 -> 写评论
    $('.remark-input').click(function(){
        layer.open({
            content: '功能正在开发中...',
            skin: 'msg',
            time: 3
        });
    })

    // 详情 -> 添加收藏
    $('.icon-stat-empty').click(function(){
    	var _this = $(this);
    	var url = _this.data('url');
    	var article_id = _this.data('article_id');
    	if (_this.hasClass('icon-stat-full')) {
    		return ;
    	}

    	$.get(url, {'id': article_id}, function(json){
    		if (json.code === 0) {
    			_this.removeClass('icon-stat-empty').addClass('icon-stat-full');
    		}

    		layer.open({
                content: json.msg,
                skin: 'msg',
                time: 3
            });
    	}, 'json')
    })

    // 详情 -> 分享再赚
    $('.article-share').click(function(){
        open_share();
    	//$('.share-modal').show().before('<div class="layui-m-layershade"></div>');
    })

    if (window['level_promote']) {
        $('body').css({
            'position': 'fixed',
            'left': '0',
            'right': '0',
            'top': '0',
            'bottom': '0',
            'overflow': 'hidden'
        });

        layer.open({
            shadeClose: false,
            className: 'layer-level-promote l' + level_promote,
            content: '<button></button>',
        });

        $('.layer-level-promote button').click(function () {
            pay_submit('/home/user/level_promote', {level: level_promote}, 'post');
        })
    }
})

function pay_ok(res) {
    if (res.msg) {
        layer.open({
            content: res.msg, skin: 'msg', time: 3, end: function () {
                location.href = location.href;
            }
        });
    } else {
        location.href = location.href;
    }
}