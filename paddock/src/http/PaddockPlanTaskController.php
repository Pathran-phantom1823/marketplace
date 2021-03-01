<?php

namespace Increment\Marketplace\Paddock\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use Increment\Marketplace\Paddock\Models\PaddockPlanTask;
use Increment\Marketplace\Paddock\Models\Paddock;
use Increment\Marketplace\Paddock\Models\SprayMix;
use Increment\Marketplace\Models\OrderRequest;
use Increment\Marketplace\Paddock\Models\Batch;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaddockPlanTaskController extends APIController
{
  
  public $paddockClass = 'Increment\Marketplace\Paddock\Http\PaddockController';
  public $cropClass = 'Increment\Marketplace\Paddock\Http\CropController';
  public $machineClass = 'Increment\Marketplace\Paddock\Http\MachineController';
  public $sprayMixClass = 'Increment\Marketplace\Paddock\Http\SprayMixController';
  public $paddockPlanClass = 'Increment\Marketplace\Paddock\Http\PaddockPlanController';
  public $batchPaddockTaskClass = 'Increment\Marketplace\Paddock\Http\BatchPaddockTaskController';
  public $orderRequestClass = 'Increment\Marketplace\Http\OrderRequestController';


  function __construct(){
    $this->model = new PaddockPlanTask();
    $this->notRequired = array();
  }
  
  public function retrieve(Request $request){
      $data = $request->all();
      $this->model = new PaddockPlanTask();
      $this->retrieveDB($data);
      for ($i=0; $i < count($this->response['data']); $i++){
           $spraymixdata= SprayMix::select('name')->where('id','=', $this->response['data'][$i]['spray_mix_id'])->get();
           if (count($spraymixdata) != 0){
              $this->response['data'][$i]['spray_mix_name'] = $spraymixdata[0]['name'];
           }
      }
      return $this->response();
  }

  public function retrieveTaskByPaddock($paddockPlanId){
      $result = PaddockPlanTask::where('paddock_plan_id', '=', $paddockPlanId)->get(['spray_mix_id', 'id', 'paddock_plan_id', 'due_date']);
      if(sizeof($result) > 0){
          return $result;
      }else{
          return null;
      }
  }

    public function retrieveMobileByParams(Request $request){
        $data = $request->all();
        $con = $data['condition'];
        if($con[1]['value'] == 'inprogress'){
            $result = PaddockPlanTask::where($con[0]['column'], '=', $con[0]['value'])
                ->where(function($query){
                    $query->where('status', '=', 'pending')
                            ->orWhere('status', '=', 'inprogress');
                })->skip($data['offset'])->take($data['limit'])->get();
        }else{
            $result = PaddockPlanTask::where($con[0]['column'], '=', $con[0]['value'])->where($con[1]['column'], '=', $con[1]['value'])->skip($data['offset'])->take($data['limit'])->get();
        }
        $temp = $result;
        if(sizeof($temp) > 0){
            $i = 0;
            foreach ($temp as $key) {
                $paddocks = app($this->paddockPlanClass)->retrievePlanByParams('id', $key['paddock_plan_id'], 'crop_id');
                $temp[$i]['paddock'] = app($this->paddockClass)->getByParams('id', $key['paddock_id'], ['id', 'name']);
                $temp[$i]['spray_mix'] = app($this->sprayMixClass)->getByParams('id', $key['paddock_id'], ['id', 'name']);
                $temp[$i]['machine'] = app($this->batchPaddockTaskClass)->getMachinedByBatches('paddock_plan_task_id', $key['id']);
                $temp[$i]['paddock']['crop_name'] = app($this->cropClass)->retrieveCropById($paddocks[0]['crop_id'])[0]->name;
                $i++;
            }
            $this->response['data'] = $temp;
        }
        return $this->response();
    }

    public function retrieveMobileByParamsEndUser(Request $request){
        $data = $request->all();
        $con = $data['condition'];
        if($con[1]['value'] == 'inprogress'){
            $result = DB::table('batches as T1')
                    ->leftJoin('batch_paddock_tasks as T2', 'T1.id', '=', 'T2.batch_id')
                    ->where('T1.'.$con[0]['column'], '=', $con[0]['value'])
                    ->where('T1.deleted_at', '=', null)
                    ->where(function($query){
                        $query->where('T1.status', '=', 'pending')
                                ->orWhere('T1.status', '=', 'inprogress');
                    })->skip($data['offset'])->take($data['limit'])->orderBy('T1.created_at', 'desc')->get();
               
        }else{
            $result = DB::table('batches as T1')
                    ->leftJoin('batch_paddock_tasks as T2', 'T1.id', '=', 'T2.batch_id')
                    ->where('T1.'.$con[0]['column'], '=', $con[0]['value'])
                    ->where('T1.'.$con[1]['column'], '=', $con[1]['value'])
                    ->where('T1.deleted_at', '=', null)
                    ->skip($data['offset'])->take($data['limit'])->orderBy('T1.created_at', 'desc')->get();
        }
        $obj = $result;
        if(sizeof($obj) > 0){
            $i = 0;
            $temp = json_decode(json_encode($obj), true);
            foreach ($temp as $key) {
                $temp[$i]['paddock'] = app($this->paddockClass)->getByParams('merchant_id', $con[0]['value'], ['id', 'name']);
                $temp[$i]['due_date'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'due_date');
                $temp[$i]['category'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'category');
                $temp[$i]['nickname'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'nickname');
                $temp[$i]['paddock_plan_id'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'paddock_plan_id');
                $temp[$i]['paddock_id'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'id');
                $temp[$i]['spray_mix_id'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'spray_mix_id');
                $temp[$i]['spray_mix'] = app($this->sprayMixClass)->getByParams('merchant_id', $con[0]['value'], ['id', 'name']);
                $temp[$i]['machine'] = app($this->machineClass)->getMachineNameByParams('id', $key['machine_id']);
                $i++;
            }
            $this->response['data'] = $temp;
        }
        return $this->response();
    }

    public function retrievePaddockPlanTaskByParamsCompleted($column, $column2, $value){
        $batch = DB::table('batches as T1')
                ->leftJoin('batch_paddock_tasks as T2', 'T1.id', '=', 'T2.batch_id')
                ->where('T1.'.$column, '=', $value)
                ->where('status', '=', 'completed')
                ->where('T1.deleted_at', '=', null)
                ->orderBy('T1.created_at', 'desc')->get()->toArray();
        $orders = OrderRequest::where($column, '=', $value)->orWhere($column2, '=', $value)->where('status', '=', 'completed')->orderBy('created_at', 'desc')->get();
        $orderArray = app($this->orderRequestClass)->manageResultsMobile($orders);
        $obj = array_merge($batch, $orderArray);
        if(sizeof($obj) > 0){
            $i = 0;
            $array = json_decode(json_encode($obj), true);
            foreach ($array as $key) {
                if(!isset($array[$i]['code'])){
                    $paddockId = $this->retrieveByParams('id', $array[$i]['paddock_plan_task_id'], 'paddock_id');
                    $array[$i]['paddock'] = $paddockId != null ? app($this->paddockClass)->getByParams('id', $paddockId, ['id', 'name']) : app($this->paddockClass)->getByParams('merchant_id', $value, ['id', 'name']);
                    $array[$i]['date_completed'] = isset($key['updated_at']) ? Carbon::createFromFormat('Y-m-d H:i:s', $key['updated_at'])->copy()->tz($this->response['timezone'])->format('d M') : null;
                    $array[$i]['nickname'] = $this->retrieveByParams('id', $array[$i]['paddock_plan_task_id'], 'nickname');
                    $array[$i]['spray_mix'] = $paddockId != null ? app($this->sprayMixClass)->getByParams('id', $paddockId, ['id', 'name']) : app($this->sprayMixClass)->getByParams('merchant_id', $value, ['id', 'name']);
                    $i++;
                }
            }
        }
        return $array;
    }

    public function retrievePaddockPlanTaskByParamsDue($column, $value){
        $result = DB::table('batches as T1')
                ->leftJoin('batch_paddock_tasks as T2', 'T1.id', '=', 'T2.batch_id')
                ->where('T1.'.$column, '=', $value)
                ->where('T1.deleted_at', '=', null)
                ->where(function($query){
                    $query->where('T1.status', '=', 'inprogress')
                            ->orWhere('T1.status', '=', 'ongoing');
                })->take(5)->orderBy('T1.created_at', 'desc')->get();
        $obj = $result;
        if(sizeof($obj) > 0){
            $i = 0;
            $temp = json_decode(json_encode($obj), true);
            foreach ($temp as $key) {
                $temp[$i]['paddock'] = app($this->paddockClass)->getByParams('merchant_id', $value, ['id', 'name']);
                $temp[$i]['category'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'category');
                $temp[$i]['nickname'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'nickname');
                $temp[$i]['paddock_plan_id'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'paddock_plan_id');
                $temp[$i]['paddock_id'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'paddock_id');
                $temp[$i]['spray_mix_id'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'spray_mix_id');
                $temp[$i]['due_date'] = $this->retrieveByParams('id', $temp[$i]['paddock_plan_task_id'], 'due_date');
                $temp[$i]['due_date_format'] = isset($temp[$i]['due_date']) ? Carbon::createFromFormat('Y-m-d', $temp[$i]['due_date'])->copy()->tz($this->response['timezone'])->format('d M') : null;
                $temp[$i]['spray_mix'] = app($this->sprayMixClass)->getByParams('merchant_id', $value, ['id', 'name']);
                $temp[$i]['machine'] = app($this->machineClass)->getMachineNameByParams('id', $key['machine_id']);
                $i++;
            }
        }
        return $temp;
    }

    public function retrievePaddockTaskByPaddock($paddockId){
        $result = Paddock::where('id', '=', $paddockId)->get();
        if(sizeof($result) > 0){
            return $result;
        }else{
            return null;
        }
    }

    public function retrieveAvailablePaddocks(Request $request){
        $data = $request->all();
        $returnResult = array();
        $result = DB::table('paddock_plans_tasks as T1')
                ->leftJoin('paddocks as T2', 'T1.paddock_id', '=', 'T2.id')
                ->leftJoin('paddock_plans as T3', 'T3.id', '=', 'T1.paddock_plan_id')
                ->leftJoin('crops as T4', 'T4.id', '=', 'T3.crop_id')
                ->leftJoin('spray_mixes as T5', 'T5.id', '=', 'T1.spray_mix_id')
                ->where('T1.spray_mix_id', '=', $data['spray_mix_id'])
                ->where('T1.status', '=', 'approved')
                ->where('T2.deleted_at', '=', null)
                ->whereNull('T2.deleted_at')
                ->where('T2.merchant_id', $data['merchant_id'])
                ->get(['T1.*', 'T2.*', 'T3.*', 'T4.name as crop_name', 'T5.name as mix_name', 'T5.application_rate', 'T5.minimum_rate', 'T5.maximum_rate', 'T1.id as plan_task_id', 'T1.deleted_at']);
        if(sizeof($result) > 0){
            $tempRes = json_decode(json_encode($result), true);
            $i = 0;
            foreach ($tempRes as $key) {
                $totalBatchArea = $this->getTotalBatchPaddockPlanTask($tempRes[$i]['plan_task_id']);
                $tempRes[$i]['area'] = (int)$tempRes[$i]['area'];
                $tempRes[$i]['remaining_area'] = $totalBatchArea != null ? ((int)$tempRes[$i]['area'] - (int)$totalBatchArea) : (int)$tempRes[$i]['area'];
                $tempRes[$i]['units'] = "Ha";
                $tempRes[$i]['spray_mix_units'] = "L/Ha";
                $tempRes[$i]['partial'] = false;
                $tempRes[$i]['partial_flag'] = false;

                $i++;
            }
            $this->response['data'] = $tempRes;
        }else{
            return $this->response['data'] = [];
        }
        return $this->response();

    }

    public function retrieveByParams($column, $value, $returns){
        $result = PaddockPlanTask::where($column, '=', $value)->where('deleted_at', '=', null)->select($returns)->get();
        return sizeof($result) > 0 ? $result[0][$returns] : null;  
    }

    public function getTotalBatchPaddockPlanTask($paddockPlanTaskId){
        $result = DB::table('batch_paddock_tasks as T1')
                ->where('T1.paddock_plan_task_id', '=', $paddockPlanTaskId)
                ->groupBy('T1.paddock_plan_task_id')
                ->select(DB::raw('SUM(T1.area) as total_area'))
                ->get();
        return sizeof($result) > 0 ? $result[0]->total_area : null;
    }
}
