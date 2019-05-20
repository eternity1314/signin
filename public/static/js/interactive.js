interactive = {
    btn_click: function (para) {
        try {
            if (!para) {
                if (window['wx_share']) {
                    var para = JSON.stringify(wx_share);
                    if (wx_share.success && typeof wx_share.success == 'function') {
                        para = JSON.parse(para);
                        para.success = 'wx_share.success()';
                        para = JSON.stringify(para);
                    }
                }
            }
            window.qushenghuo.def_share(para)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.def_share.postMessage(para)
        } catch (error) {
            console.log(error)
        }
    },
    goods_link_click: function () {
        try {
            window.qushenghuo.goto_goods_link()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.goto_goods_link.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    poster_share_click: function (url) {
        try {
            window.qushenghuo.share(url)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.share.postMessage(url)
        } catch (error) {
            console.log(error)
        }
    },
    copy: function (content) {
        try {
            window.qushenghuo.copy(content)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.copy.postMessage(content)
        } catch (error) {
            console.log(error)
        }
    },
    zoom_image: function (url) {
        try {
            window.qushenghuo.zoomImage(url)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.zoomImage.postMessage(url)
        } catch (error) {
            console.log(error)
        }
    },
    showShareButton: function (show) {
        try {
            window.qushenghuo.showShareButton(show)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.showShareButton.postMessage(show)
        } catch (error) {
            console.log(error)
        }
    },
    jumpEarly: function () {
        try {
            window.qushenghuo.jumpEarly()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpEarly.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    jumpProductDetail: function (v) {
        try {
            window.qushenghuo.jumpProductDetail(v)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpProductDetail.postMessage(v)
        } catch (error) {
            console.log(error)
        }
    },
    jumpFreeOrderPage: function () {
        try {
            window.qushenghuo.jumpFreeOrderPage()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpFreeOrderPage.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    jumpAllIncomePage: function () {
        try {
            window.qushenghuo.jumpAllIncomePage()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpAllIncomePage.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    jumpWithdrawPage: function () {
        try {
            window.qushenghuo.jumpWithdrawPage()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpWithdrawPage.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    jumpAlreadyWithdrawPage: function () {
        try {
            window.qushenghuo.jumpAlreadyWithdrawPage()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpAlreadyWithdrawPage.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    share: function (url) {
        try {
            window.qushenghuo.share(url)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.share.postMessage(url)
        } catch (error) {
            console.log(error)
        }
    },
    jumpMine: function () {
        try {
            window.qushenghuo.jumpMine()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.jumpMine.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    showPayDialog: function () {
        try {
            window.qushenghuo.showPayDialog()
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.showPayDialog.postMessage(null)
        } catch (error) {
            console.log(error)
        }
    },
    redPacketWithdraw: function (b) {
        var data = JSON.stringify({"needRequestPacket": b});

        try {
            window.qushenghuo.redPacketWithdraw(data)
        } catch (error) {
            console.log(error)
        }

        try {
            window.webkit.messageHandlers.redPacketWithdraw.postMessage(data)
        } catch (error) {
            console.log(error)
        }
    }
}

function def_share(para) {

}

function goto_goods_link() {

}

function share(url) {

}

function copy() {

}

function zoomImage() {

}

function showShareButton() {

}

function jumpEarly() {

}

function jumpProductDetail() {

}

function jumpFreeOrderPage() {

}