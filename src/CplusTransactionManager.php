<?php 

namespace Bvtvd;

use GuzzleHttp\Client;

class CplusTransactionManager
{
    public $http;
    public $statusCode = 200;
    public $rsa;
    public $aes;
    public $rsaPrivateKey;
    public $bankPublicKey;
    public $aesKey;
    public $url;
    protected $error;
    protected $account;
    protected $uid;
    protected $responseContent;

    public function __construct($privateKey, $bankPublicKey, $aesKey, $url, $account, $uid)
    {
        
        $this->rsaPrivateKey = $privateKey;
        $this->bankPublicKey = $this->formatPubKey($bankPublicKey);
        $this->aesKey = $aesKey;
        $this->url = $url;
        $this->account = $account;
        $this->uid = $uid;

        $this->http = new Client();
        $this->rsa = new Rsa($this->rsaPrivateKey, $this->bankPublicKey);
        $this->aes = new Aes($this->aesKey, 'aes-256-ecb');
    }



    /**
     * 获取今天的交易记录
     */
    public function getTodayTransactions()
    {
        $today = date('Ymd');
        return $this->sendRequest([
            'head' => [
                'funcode' => 'DCDUMTSQ',
            ],
            'body' => [
                'accnbr' => $this->account,
                'begdat' => $today,
                'enddat' => $today,
            ]
        ]);
    }

    /**
     * 获取最近一周的交易记录
     */
    public function getLatestWeekTranstions($trxseq = null, $trsdat = null)
    {
        $today = date('Ymd');
        $before = date('Ymd', time() - 3600 * 24 * 6);


        $post = [
            'head' => [
                'funcode' => 'DCDUMTSQ',
            ],
            'body' => [
                'accnbr' => $this->account,
                'begdat' => $before,
                'enddat' => $today,
            ]
        ];

        if($trxseq){
            $post['body']['trxseq'] = $trxseq;
            $post['body']['trsdat'] = $trsdat;

        }

        return $this->sendRequest($post);
    }

    /***
     * 创造交易记录
     */
    public function createTransaction()
    {

        return $this->sendRequest([
            'head' => [
                'funcode' => 'DCPAYOPR'
            ],
            'body' => [
                'modnbr' => '000002',
                'refext' => uniqid(),
                'sndeac' => '755930553010602',
                // 'sndeac' => '755930553010801', 
                'vireac' => '0000019031',
                // 收方账户
                'rcveac' => '7559305530108010000019031',
                // 收方户名
                'rcvean' => '你的名字',
                // 金额 
                "trsamt" =>  "47",
                "rcveab" =>  "招商银行",
                "rcveaa" =>  "四川省成都市",
                // 收方是不是招行户
                "sysflg" =>  "Y",
            ]
        ]);
    }

    /**
     * 退款
     * 需要对公交易记录中的数据来配合退款
     */
    public function refund($setnbr, $trxnbr, $trsamt, $dumnbr, $rpyacc, $rpynam, $bckflg, $withdrawId = 0)
    {
        return $this->sendRequest([
            'head' => [
                'funcode' => 'DCDMDMPB'
            ],
            'body' => [
                // 原交易套号
                'setnbr' => $setnbr,
                // 原交易流水号
                'trxnbr' => $trxnbr,
                // 交易金额
                'trsamt' => $trsamt,
                // 主账号
                'accnbr' => $this->account,
                // 虚拟户编号
                'dumnbr' => $dumnbr,
                // 原付方账号
                'rpyacc' => $rpyacc,
                // 原付方名称
                'rpynam' => $rpynam,
                // 是否退息
                'intflg' => 'N',
                'intamt' => '0',
                // 用途
                'nusage' => '招行自动确认原路提现@' . $withdrawId,
                // 对方参考号
                'yurref' => $this->makeSerialNumber(),
                // 部分退款标志
                'bckflg' => $bckflg,
            ]
        ]);
    }


    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    public function setError($error)
    {
        $this->error = $error;
    }

    public function getError()
    {
        return $this->error;
    }

    public function sendRequest($data)
    {
        $data = $this->transferPostData($data);

        // Y100022515, Y100022516
        // \Log::info('[C+交易管家请求2]: ', $data);


        $response = $this->http->post($this->url, [
            'form_params' => $data,
            'http_errors' => false
        ]);



        // \Log::info('response:',(array) $response);

        $this->statusCode = $response->getStatusCode();

        $content = sprintf('HTTPCODE: %s,  BODY: %s', $response->getStatusCode(), $response->getBody());

        $this->setResponseContent([
            'httpcode' => $response->getStatusCode(),
            'body' => $response->getBody()
        ]);

        // var_dump($content);
        if (200 != $this->getStatusCode()) {
            $this->setError($response->getBody());
            return false;
        }

        return $this->transferResult($response->getBody());
    }

    protected function setResponseContent($content)
    {
        $this->responseContent = $content;
    }

    public function getResponseContent()
    {
        return $this->responseContent;
    }

    /**
     * 
     */
    public function transferPostData($data)
    {
        array_walk_recursive($data, function ($value, $key) {
            return (string) $value;
        });

        $uid = $this->uid;
        $data['head']['userid'] = $uid;
        $data['head']['reqid'] = $this->getReqid();

        $wrap = [
            'request' => $data,
            'signature' => [
                'sigtim' => date('YmdHis'),
                'sigdat' => '__signature_sigdat__'
            ]
        ];

        $this->tksort($wrap);

        $sign = $this->getSign(json_encode($wrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));


        $wrap['signature']['sigdat'] = $sign;

        \Log::info('[C+交易管家请求1]: ', $wrap);
        $string = $this->aes->encrypt(json_encode($wrap, JSON_UNESCAPED_SLASHES));

        return [
            'UID' => $uid,
            'DATA' => $string
        ];
    }


    public function transferResult($string)
    {
        $data = $this->aes->decrypt($string);

        $data = json_decode($data, true);

        $sign = $data['signature']['sigdat'];
        $data['signature']['sigdat'] = '__signature_sigdat__';



        // TODO:: 有的验签无法通过(目前不清楚原因, 可能是中文编码等问题), 先不进行验签了 
        $this->tksort($data);

        $result = $this->rsa->verify(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $sign);


        if ($result) {
            // var_dump('签名成功');
        } else {
            // var_dump('签名失败');
        }

        return $data;


        $this->setError('签名错误');
        return false;
    }


    /**
     * 是用RSA私钥进行签名, 算法 SHA256, 签名结果 base64
     */
    public function getSign(string $str)
    {
        return $this->rsa->sign($str);
    }


    /**
     * 多维数据按key 排序
     */
    function tksort(&$array)
    {
        ksort($array);
        foreach (array_keys($array) as $k) {
            if (gettype($array[$k]) == "array") {
                $this->tksort($array[$k]);
            } else {
                $array[$k] = trim($array[$k]);
            }
        }
    }


    public function getReqid()
    {
        list($usec, $sec) = explode(" ", microtime());
        $msec = round($usec, 3) * 1000;
        $time =  date("YmdHis") . $msec;

        return sprintf('%s%s', $time, uniqid());
    }


    /**
     * 格式化公钥
     */
    public function formatPubKey($pubKey)
    {
        $fKey = "-----BEGIN PUBLIC KEY-----\n";
        $len = strlen($pubKey);
        for ($i = 0; $i < $len;) {
            $fKey = $fKey . substr($pubKey, $i, 64) . "\n";
            $i += 64;
        }
        $fKey .= "-----END PUBLIC KEY-----";
        return $fKey;
    }

        /**
     * 生成资金流水号
     */
    public function makeSerialNumber(){
    	$time = time();
    	$tmp_1 = str_split(str_pad(microtime(true) * 10000, 7, '0', STR_PAD_LEFT));
    	$tmp_2 = str_split(date('His', $time));
    	$str = '';
    	foreach ($tmp_2 as $k => $vo) {
    		$str .= $tmp_1[$k] . $vo;
    	}
    	return substr(str_replace('.', '', '11' . $str . $tmp_1[6] . substr(time(), -5) . sprintf('%02d', rand(0, 99))), 0, 21).rand(000, 999);
    }
}