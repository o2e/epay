<?php

namespace app\command;


use app\pay\model\CenterPayModel;
use app\pay\model\WxPayModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class Test extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('test')->setDescription('user test');
        // 设置参数
    }

    protected function execute(Input $input, Output $output)
    {
        // 指令输出
        $systemPayConfig   = getConfig()['alipay'];
        $config            = $systemPayConfig;
        $config['gateway'] = 'http://center.zmz999.com';
        $centerPayModel    = new CenterPayModel($config);
        dump($centerPayModel->getPayApiList(2));
//        $test = 'Mozilla/5.0 (Linux; Android 9; MIX 2S Build/PKQ1.180729.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/66.0.3359.126 MQQBrowser/6.2 TBS/044606 Mobile Safari/537.36 V1_AND_SQ_7.9.9_1010_YYB_D QQ/7.9.9.3965 NetType/4G WebP/0.3.0 Pixel/1080 StatusBarHeight/76';
//        dump(strpos($test, 'QQ/') !== false);

//        $test = new CenterPayModel([
//            'gateway' => 'http://center.pay.cn',
//            'epayCenterUid'     => 1,
//            'epayCenterKey'     => '8d969eef6ecad3c29a3a629280e686cf0c3f5d5a86aff3ca12020c923adc6c92'
//        ]);
////        dump($test->getPayStatus('233'));
//        dump($test->getPayUrl('12329', 'bankpay', '10', 'http://center.zmz999.com/test'));
//        $orderList = Db::table('epay_order')->where('status', 1)->field('tradeNo')->whereTime('endtime', '>=', '2019-4-2 12:30:00')->whereTime('endtime', '<=', '2019-4-2 13:30:59')->select(false);
//        exit(dump(buildCallBackUrl('2019032915384048736','notify')));
    }

    private function buildCallBackUrlA(string $tradeNo, string $type)
    {
        $type = strtolower($type);
        if ($type != 'notify' && $type != 'return')
            return '1';
        //type is error
        $orderData = \think\Db::table('pay_order')->where('trade_no', $tradeNo)->field('pid,trade_no,out_trade_no,type,name,money,' . $type . '_url')->limit(1)->select();
        if (empty($orderData))
            return '2';
        //order type
        $orderData = $orderData[0];

        $userKey = \think\Db::table('pay_user')->where('id', $orderData['pid'])->field('key')->limit(1)->select();
        if (empty($userKey))
            $userKey = '3';
        else
            $userKey = $userKey[0]['key'];
        //get user key
//兼容层
        $args        = [
            'pid'          => $orderData['pid'],
            'trade_no'     => $orderData['trade_no'],
            'out_trade_no' => $orderData['out_trade_no'],
            'type'         => $orderData['type'],
            'name'         => $orderData['name'],
            'money'        => $orderData['money'],
            'trade_status' => 'TRADE_SUCCESS'
        ];
        $args        = argSort(paraFilter($args));
        $sign        = signMD5(createLinkString($args), $userKey);
        $callBackUrl = $orderData[$type . '_url'] . (strpos($orderData[$type . '_url'], '?') ? '&' : '?') . createLinkStringUrlEncode($args) . '&sign=' . $sign . '&sign_type=MD5';
        return $callBackUrl;
    }

    protected function curl($url = '', $addHeaders = [], $requestType = 'get', $requestData = '', $postType = '', $urlEncode = true)
    {
        if (empty($url))
            return '';
        //容错处理
        $headers  = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36'
        ];
        $postType = strtolower($postType);
        if ($requestType != 'get') {
            if ($postType == 'json') {
                $headers[]   = 'Content-Type: application/json; charset=utf-8';
                $requestData = is_array($requestData) ? json_encode($requestData) : $requestData;
            } else if ($postType == 'xml') {
                $headers[] = 'Content-Type:text/xml; charset=utf-8';
            }
            $headers[] = 'Content-Length: ' . strlen($requestData);
        }
        if ($requestType == 'get' && is_array($requestData)) {
            $tempBuff = '';
            foreach ($requestData as $key => $value) {
                $tempBuff .= $key . '=' . $value . '&';
            }
            $tempBuff = trim($tempBuff, '&');
            $url      .= '?' . $tempBuff;
        }
        //手动build get请求参数

        if (!empty($addHeaders))
            $headers = array_merge($headers, $addHeaders);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        //设置允许302转跳
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
//        curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
//        curl_setopt($ch, CURLOPT_PROXY, '116.255.172.156'); //代理服务器地址
//        curl_setopt($ch, CURLOPT_PROXYPORT, 16819); //代理服务器端口
        //set proxy
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        //gzip

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //add ssl
        if ($requestType == 'get') {
            curl_setopt($ch, CURLOPT_HEADER, false);
        } else if ($requestType == 'post') {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($requestType));
        }
        //处理类型
        if ($requestType != 'get') {
            if (is_array($requestData) && !empty($requestData)) {
                $temp = '';
                foreach ($requestData as $key => $value) {
                    if ($urlEncode) {
                        $temp .= rawurlencode(rawurlencode($key)) . '=' . rawurlencode(rawurlencode($value)) . '&';
                    } else {
                        $temp .= $key . '=' . $value . '&';
                    }
                }
                $requestData = substr($temp, 0, strlen($temp) - 1);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
        }
        //只要不是get姿势都塞东西给他post
        $result   = curl_exec($ch);
        $errorMsg = '';
        if ($result === false)
            $errorMsg = curl_error($ch);
        curl_close($ch);

        return ['isSuccess' => ($result !== false), 'errorMsg' => $errorMsg];
    }
}
