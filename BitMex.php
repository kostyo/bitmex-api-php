<?php
/*
 * BitMex PHP REST API
 *
 * @author y0un1verse
 * @version 0.1
 * @link https://github.com/y0un1verse/bitmex-api-php
 */
class BitMex {
  //const API_URL = 'https://testnet.bitmex.com';
  const API_URL = 'https://www.bitmex.com';
  const API_PATH = '/api/v1/';
  const SYMBOL = 'XBTUSD';
  private $apiKey;
  private $apiSecret;
  private $ch;
  /*
   * @param string $apiKey    API Key
   * @param string $apiSecret API Secret
   */
  public function __construct($apiKey = '', $apiSecret = '') {
    $this->apiKey = $apiKey;
    $this->apiSecret = $apiSecret;
    $this->curlInit();
  }
  /*
   * Public
   */
  /*
   * Get Ticker
   *
   * @return ticker array
   */
  public function getTicker() {
    $symbol = self::SYMBOL;
    $data['function'] = "instrument";
    $data['params'] = array(
      "symbol" => $symbol
    );
    $return = $this->publicQuery($data);
    if(!$return || count($return) != 1 || !isset($return[0]['symbol'])) return false;
    $return = array(
      "symbol" => $return[0]['symbol'],
      "last" => $return[0]['lastPrice'],
      "bid" => $return[0]['bidPrice'],
      "ask" => $return[0]['askPrice'],
      "high" => $return[0]['highPrice'],
      "low" => $return[0]['lowPrice']
    );
    return $return;
  }
  /*
   * Get Candles
   *
   * Get candles history
   *
   * @param $timeFrame can be 1m 5m 1h
   * @param $count candles count
   *
   * @return candles array (from past to present)
   */
  public function getCandles($timeFrame,$count) {
    $symbol = self::SYMBOL;
    $data['function'] = "trade/bucketed";
    $data['params'] = array(
      "symbol" => $symbol,
      "count" => $count,
      "binSize" => $timeFrame,
      "partial" => "false",
      "reverse" => "true"
    );
    $return = $this->publicQuery($data);
    $candles = array();
    // Converting
    foreach($return as $item) {
      $time = strtotime($item['timestamp']); // Unix time stamp
      $candles[$time] = array(
        'timestamp' => date('Y-m-d H:i:s',$time), // Local time human-readable time stamp
        'time' => $time,
        'open' => $item['open'],
        'high' => $item['high'],
        'close' => $item['close'],
        'low' => $item['low']
      );
    }
    // Sorting candles from the past to the present
    ksort($candles);
    return $candles;
  }
  /*
   * Get Order
   *
   * Get order by order ID
   *
   * @return array or false
   */
  public function getOrder($orderID,$count = 100) {
    $symbol = self::SYMBOL;
    $data['method'] = "GET";
    $data['function'] = "order";
    $data['params'] = array(
      "symbol" => $symbol,
      "count" => $count,
      "reverse" => "true"
    );
    $orders = $this->authQuery($data);
    foreach($orders as $order) {
      if($order['orderID'] == $orderID) {
        return $order;
      }
    }
    return false;
  }
  /*
   * Get Orders
   *
   * Get last 100 orders
   *
   * @return orders array (from the past to the present)
   */
  public function getOrders($count = 100) {
    $symbol = self::SYMBOL;
    $data['method'] = "GET";
    $data['function'] = "order";
    $data['params'] = array(
      "symbol" => $symbol,
      "count" => $count,
      "reverse" => "true"
    );
    return array_reverse($this->authQuery($data));
  }
  /*
   * Get Open Orders
   *
   * Get open orders from the last 100 orders
   *
   * @return open orders array
   */
  public function getOpenOrders() {
    $symbol = self::SYMBOL;
    $data['method'] = "GET";
    $data['function'] = "order";
    $data['params'] = array(
      "symbol" => $symbol,
      "reverse" => "true"
    );
    $orders = $this->authQuery($data);
    $openOrders = array();
    foreach($orders as $order) {
      if($order['ordStatus'] == 'New' || $order['ordStatus'] == 'PartiallyFilled') $openOrders[] = $order;
    }
    return $openOrders;
  }
  /*
   * Get Open Positions
   *
   * Get all your open positions
   *
   * @return open positions array
   */
  public function getOpenPositions() {
    $symbol = self::SYMBOL;
    $data['method'] = "GET";
    $data['function'] = "position";
    $data['params'] = array(
      "symbol" => $symbol
    );
    $positions = $this->authQuery($data);
    $openPositions = array();
    foreach($positions as $position) {
      if(isset($position['isOpen']) && $position['isOpen'] == true) {
        $openPositions[] = $position;
      }
    }
    return $openPositions;
  }
  /*
   * Edit Order Price
   *
   * Edit you open order price
   *
   * @param $orderID    Order ID
   * @param $price      new price
   *
   * @return new order array
   */
  public function editOrderPrice($orderID,$price) {
    $data['method'] = "PUT";
    $data['function'] = "order";
    $data['params'] = array(
      "orderID" => $orderID,
      "price" => $price
    );
    return $this->authQuery($data);
  }

  /*
   * Edit Order Price NOW
   *
   * Edit you open order price
   *
   * @param $orderID    Order ID
   * @param $price      new price
   *
   * @return new order array
   */
  public function editOrderPriceNow($getOrderID,$position) {
    $editOrder = array();
    while ($editOrder != TRUE) {
      $priceNow = array();
      while ($priceNow != TRUE){
        $priceNow=$this->getTicker("XBTUSD");
        sleep(2);
      }
      if ($position =='buy') {$priceNow = $priceNow["bid"];}
      else {$priceNow = $priceNow["ask"];}
      echo "Get price OK. Price=".$priceNow."\n";
      sleep(2);
      echo ($getOrderID.'\n');
      $editOrder=$this->editOrderPrice($getOrderID,$priceNow);
      sleep(2);
      }
    echo ("Edit Order OK \n");
  }


  /*
   * Create Order
   *
   * Create new market order
   *
   * @param $type can be "Limit"
   * @param $side can be "Buy" or "Sell"
   * @param $price BTC price in USD
   * @param $quantity should be in USD (number of contracts)
   * @param $maker forces platform to complete your order as a 'maker' only
   *
   * @return new order array
   */
  public function createOrder($type,$side,$price,$quantity,$maker = true) {
    $symbol = self::SYMBOL;
    $data['method'] = "POST";
    $data['function'] = "order";
    $data['params'] = array(
      "symbol" => $symbol,
      "side" => $side,
      "price" => $price,
      "orderQty" => $quantity,
      "ordType" => $type
    );
    if($maker) {
      $data['params']['execInst'] = "ParticipateDoNotInitiate";
    }
    return $this->authQuery($data);
  }
  /*
   * Cancel All Open Orders
   *
   * Cancels all of your open orders
   *
   * @param $text is a note to all closed orders
   *
   * @return all closed orders arrays
   */
  public function cancelAllOpenOrders($text = "") {
    $symbol = self::SYMBOL;
    $data['method'] = "DELETE";
    $data['function'] = "order/all";
    $data['params'] = array(
      "symbol" => $symbol,
      "text" => $text
    );
    return $this->authQuery($data);
  }
  /*
   * Get Wallet
   *
   * Get your account wallet
   *
   * @return array
   */
  public function getWallet() {
    $data['method'] = "GET";
    $data['function'] = "user/wallet";
    $data['params'] = array(
      "currency" => "XBt"
    );
    return $this->authQuery($data);
  }
  /*
   * Get Margin
   *
   * Get your account margin
   *
   * @return array
   */
  public function getMargin() {
    $data['method'] = "GET";
    $data['function'] = "user/margin";
    $data['params'] = array(
      "currency" => "XBt"
    );
    return $this->authQuery($data);
  }
  /*
   * Private
   *
   */
  /*
   * Auth Query
   *
   * Query for authenticated queries only
   *
   * @param $data consists method (GET,POST,DELETE,PUT),function,params
   *
   * @return return array
   */
  private function authQuery($data) {
    $method = $data['method'];
    $function = $data['function'];
    if($method == "GET" || $method == "POST" || $method == "PUT") {
      $params = http_build_query($data['params']);
    }
    elseif($method == "DELETE") {
      $params = json_encode($data['params']);
    }
    $path = self::API_PATH . $function;
    $url = self::API_URL . self::API_PATH . $function;
    if($method == "GET" && count($data['params']) >= 1) {
      $url .= "?" . $params;
      $path .= "?" . $params;
    }
    $nonce = $this->generateNonce();
    if($method == "GET") {
      $post = "";
    }
    else {
      $post = $params;
    }
    $sign = hash_hmac('sha256', $method.$path.$nonce.$post, $this->apiSecret);
    $headers = array();
    $headers[] = "api-signature: $sign";
    $headers[] = "api-key: {$this->apiKey}";
    $headers[] = "api-nonce: $nonce";
    $headers[] = 'Connection: Keep-Alive';
    $headers[] = 'Keep-Alive: 90';
    curl_reset($this->ch);
    curl_setopt($this->ch, CURLOPT_URL, $url);
    if($data['method'] == "POST") {
      curl_setopt($this->ch, CURLOPT_POST, true);
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
    }
    if($data['method'] == "DELETE") {
      curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "DELETE");
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
      $headers[] = 'X-HTTP-Method-Override: DELETE';
    }
    if($data['method'] == "PUT") {
      curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "PUT");
      //curl_setopt($this->ch, CURLOPT_PUT, true);
      curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
      $headers[] = 'X-HTTP-Method-Override: PUT';
    }
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER , false);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    $return = curl_exec($this->ch);
    if(!$return) {
      return $this->curlError();
    }
    $return = json_decode($return,true);
    if(isset($return['error'])) {
      return $this->platformError($return);
    }
    return $return;
  }
  /*
   * Public Query
   *
   * Query for public queries only
   *
   * @param $data consists function,params
   *
   * @return return array
   */
  private function publicQuery($data) {
    $function = $data['function'];
    $params = http_build_query($data['params']);
    $url = self::API_URL . self::API_PATH . $function . "?" . $params;;
    $headers = array();
    $headers[] = 'Connection: Keep-Alive';
    $headers[] = 'Keep-Alive: 90';
    curl_reset($this->ch);
    curl_setopt($this->ch, CURLOPT_URL, $url);
    curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER , false);
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    $return = curl_exec($this->ch);
    if(!$return) {
      return $this->curlError();
    }
    $return = json_decode($return,true);
    if(isset($return['error'])) {
      return $this->platformError($return);
    }
    return $return;
  }
  /*
   * Generate Nonce
   *
   * @return string
   */
  private function generateNonce() {
    $nonce = (string) number_format(round(microtime(true) * 100000), 0, '.', '');
    return $nonce;
  }
  /*
   * Curl Init
   *
   * Init curl header to support keep-alive connection
   */
  private function curlInit() {
    $this->ch = curl_init();
  }
  /*
   * Curl Error
   *
   * @return false
   */
  private function curlError() {
    if ($errno = curl_errno($this->ch)) {
      $errorMessage = curl_strerror($errno);
      echo "cURL error ({$errno}) : {$errorMessage}\n";
      return false;
    }
    return false;
  }
  /*
   * Platform Error
   *
   * @return false
   */
  private function platformError($return) {
    echo "BitMex error ({$return['error']['name']}) : {$return['error']['message']}\n";
    return false;
  }
}
