wxpay_jsapi_param = window['wxpay_jsapi_param'] || {};

function jsApiCall() {
    WeixinJSBridge.invoke(
        'getBrandWCPayRequest',
        wxpay_jsapi_param,
        function (res) {
            if (res.err_msg == "get_brand_wcpay_request:ok") {
                if (typeof pay_ok != "undefined") {
                    pay_ok('wxpay');
                }
            } else if (res.err_msg == "get_brand_wcpay_request:cancel") {
                if (typeof pay_cancel != "undefined") {
                    pay_cancel('wxpay');
                }
            } else if (res.err_msg == "get_brand_wcpay_request:fail") {
                if (typeof pay_fail != "undefined") {
                    pay_fail('wxpay');
                }
            }
        }
    );
}

function callpay() {
    var param_type = typeof wxpay_jsapi_param;
    if (param_type == "undefined") {
        alert("pay param empty");
        return false;
    } else if (param_type == "string") {
        wxpay_jsapi_param = JSON.parse(wxpay_jsapi_param);
    }

    if (typeof WeixinJSBridge == "undefined") {
        if (document.addEventListener) {
            document.addEventListener('WeixinJSBridgeReady', jsApiCall, false);
        } else if (document.attachEvent) {
            document.attachEvent('WeixinJSBridgeReady', jsApiCall);
            document.attachEvent('onWeixinJSBridgeReady', jsApiCall);
        }
    } else {
        jsApiCall();
    }
}

// function to_pay() {
//     wxpay_jsapi_param = parent.wxpay_jsapi_param;
//     console.log(wxpay_jsapi_param);
//     callpay();
// }

// if (is_parent) to_pay();