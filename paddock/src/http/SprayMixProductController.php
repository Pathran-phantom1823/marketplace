<?php

namespace Increment\Marketplace\Paddock\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Marketplace\Paddock\Models\SprayMixProduct;
use Increment\Marketplace\Paddock\Models\SprayMix;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SprayMixProductController extends APIController
{
    public $productClass = 'Increment\Marketplace\Http\ProductController';
    //
    function __construct(){
      $this->model = new SprayMixProduct();
      $this->notRequired = array();
    }

    public function retrieveDetails(Request $request){
      $data = $request->all();
      $result = DB::table("spray_mix_products AS T1")
                ->select("T1.rate","T1.status","T1.created_at AS spray_mix_prod_created", "T2.title AS product_name", "T3.application_rate", "T3.minimum_rate", "T3.maximum_rate", "T3.name AS spray_mix_name")
                ->leftJoin("products AS T2", "T1.product_id", "=", "T2.id")
                ->leftJoin("spray_mixes AS T3", "T1.spray_mix_id", "=", "T3.id")
                ->where("T1.id", "=", $data['id'])
                ->get();
      $this->response['data'] = $result;
      return $this->response();
    }

  public function retrieveSprayMixProducts(Request $request){
    $data = $request->all();
    $sprayMix = SprayMix::where('id', '=', $data['mix_id'])->get();
    $result = array();
    if(sizeof($sprayMix) > 0){
      $i = 0;
      $sprayMixProduct = SprayMixProduct::where('spray_mix_id', '=', $data['mix_id'])->get();
      if(sizeof($sprayMixProduct) > 0){
        $k = 0;
        $result[$i]['products'] = app($this->productClass)->getProductName('id', $sprayMixProduct[$k]['product_id']);
        $k++;
      }
      $result[$i]['application_rate'] = $sprayMix[0]['application_rate'];
    }
    $this->response['data'] = $result;
    return $this->response();
  }

  public function retrieveByParams(Request $request){
    $data = $request->all();
    $this->model = new SprayMixProduct();
    $this->retrieveDB($data);
    for ($i=0; $i < count($this->response['data']); $i++){
      $item = $this->response['data'][$i];
      $this->response['data'][$i]['product'] = app($this->productClass)->getProductName('id', $item['product_id']);
    }
    return $this->response();
  }
}
