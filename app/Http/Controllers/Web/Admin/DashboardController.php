<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Web\BaseController;
use App\Models\Admin\Driver;
use App\Models\Request\Request;
use App\Models\Request\RequestBill;
use App\Models\Request\Request as RequestRequest;
use App\Models\User;
use App\Base\Constants\Auth\Role;
use App\Models\Admin\Zone;
use App\Base\Constants\Auth\Role as RoleSlug;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use App\Base\Constants\Setting\Settings;
use App\Models\Admin\AdminDetail;
use App\Models\Admin\VehicleType;
use App\Models\Request\RequestPlace;

class DashboardController extends BaseController
{

    public function fetchDriverDashboardMap(){
        $items = RequestRequest::whereHas('zoneType.zone')->get();
        // dd($items);
        foreach ($items as $value) {
            $result = $value;
        }
        return $result;
    }

    public function dashboard(Request $request)
    {

        if(!Session::get('applocale')){
            Session::put('applocale', 'en');
        }

        $ownerId = null;
        if (auth()->user()->hasRole('admin')) {
            $ownerId = auth()->user()->admin->id;
        }

        $page = trans('pages_names.dashboard');
        $main_menu = 'dashboard';
        $sub_menu = null;

        $today = date('Y-m-d');

        // if (access()->hasRole(RoleSlug::SUPER_ADMIN)) {
            $total_drivers = Driver::where('approve', 2)->where('owner_id', null)->count();
            $total_waiting_drivers = Driver::where('approve', 0)->where('owner_id', null)->count();
            $total_aproved_drivers = Driver::where('approve', 1)->where('owner_id', null)->count();
            $total_vehicleType = VehicleType::count();
            $total_admin = AdminDetail::count();
            $total_booking = RequestRequest::companyKey()->where('transport_type','taxi')->count();
        // }else{
        //     $total_drivers = Driver::where('approve', 2)->where('company_key', auth()->user()->company_key)->where('company_key', '!=', null)->count();

        //     $total_aproved_drivers = Driver::where('approve', 1)->where('company_key', auth()->user()->company_key)->where('company_key', '!=', null)->count();

        //     $total_vehicleType = VehicleType::where('company_key', auth()->user()->company_key)->where('company_key', '!=', null)->count();

        //     $total_waiting_drivers = Driver::where('approve', 0)->where('company_key', auth()->user()->company_key)->where('company_key', '!=', null)->where('owner_id', null)->count();

        //     $total_booking = RequestRequest::companyKey()->where('transport_type','taxi')->count();
        //     // dd($total_booking);
        //     $total_admin = '';
        // }


        // $total_drivers = Driver::selectRaw('
        //         IFNULL(SUM(CASE WHEN approve=1 THEN 1 ELSE 0 END),0) AS approved,
        //         IFNULL((SUM(CASE WHEN approve=1 THEN 1 ELSE 0 END) / count(*)),0) * 100 AS approve_percentage,
        //         IFNULL((SUM(CASE WHEN approve=0 THEN 1 ELSE 0 END) / count(*)),0) * 100 AS decline_percentage,
        //         IFNULL(SUM(CASE WHEN approve=0 THEN 1 ELSE 0 END),0) AS declined,
        //         count(*) AS total
        //     ')
        // ->whereHas('user', function ($query) {
        //     $query->auth()->user()->company_key;
        // });

        // if($ownerId != null){
        //     $total_drivers = $total_drivers->whereOwnerId($ownerId);
        // }

        // $total_drivers = $total_drivers->get();

        $total_users = User::belongsToRole('user')->companyKey()->count();

        //trips
        if($ownerId != null){
        $trips = Request::companyKey()->selectRaw('
                    IFNULL(SUM(CASE WHEN is_completed=1 THEN 1 ELSE 0 END),0) AS today_completed,
                    IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END),0) AS today_cancelled,
                    IFNULL(SUM(CASE WHEN is_completed=0 AND is_cancelled=0 THEN 1 ELSE 0 END),0) AS today_scheduled,
                    IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) AS auto_cancelled,
                    IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) AS user_cancelled,
                    IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0) AS driver_cancelled,
                    (IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) +
                    IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) +
                    IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0)) AS total_cancelled
                ')
                ->whereDate('trip_start_time',$today)
                ->where('requests.owner_id',$ownerId)
                ->get();
        }else{
            $trips = Request::companyKey()->selectRaw('
                IFNULL(SUM(CASE WHEN is_completed=1 THEN 1 ELSE 0 END),0) AS today_completed,
                IFNULL(SUM(CASE WHEN is_cancelled=1 THEN 1 ELSE 0 END),0) AS today_cancelled,
                IFNULL(SUM(CASE WHEN is_completed=0 AND is_cancelled=0 THEN 1 ELSE 0 END),0) AS today_scheduled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) AS auto_cancelled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) AS user_cancelled,
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0) AS driver_cancelled,
                (IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=0 THEN 1 ELSE 0 END),0) +
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=1 THEN 1 ELSE 0 END),0) +
                IFNULL(SUM(CASE WHEN is_cancelled=1 AND cancel_method=2 THEN 1 ELSE 0 END),0)) AS total_cancelled
            ')
            ->whereDate('trip_start_time',$today)
            ->get();
        }
        $cardEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=0,request_bills.total_amount,0)),0)";
        $cashEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=1,request_bills.total_amount,0)),0)";
        $walletEarningsQuery = "IFNULL(SUM(IF(requests.payment_opt=2,request_bills.total_amount,0)),0)";
        $adminCommissionQuery = "IFNULL(SUM(request_bills.admin_commision_with_tax),0)";
        $driverCommissionQuery = "IFNULL(SUM(request_bills.driver_commision),0)";
        $totalEarningsQuery = "$cardEarningsQuery + $cashEarningsQuery + $walletEarningsQuery";

        // Today earnings
        if($ownerId != null){
            $todayEarnings =Request::leftJoin('request_bills','requests.id','request_bills.request_id')
                                        ->selectRaw("
                                        {$cardEarningsQuery} AS card,
                                        {$cashEarningsQuery} AS cash,
                                        {$walletEarningsQuery} AS wallet,
                                        {$totalEarningsQuery} AS total,
                                        {$adminCommissionQuery} as admin_commision,
                                        {$driverCommissionQuery} as driver_commision,
                                        IFNULL(({$cardEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS card_percentage,
                                        IFNULL(({$cashEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS cash_percentage,
                                        IFNULL(({$walletEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS wallet_percentage
                                    ")
                                    ->companyKey()
                                    ->where('requests.is_completed',true)
                                    ->where('requests.owner_id',$ownerId)
                                    ->whereDate('requests.trip_start_time',date('Y-m-d'))
                                    ->get();
        }else{
        $todayEarnings = Request::leftJoin('request_bills','requests.id','request_bills.request_id')
                                        ->selectRaw("
                                        {$cardEarningsQuery} AS card,
                                        {$cashEarningsQuery} AS cash,
                                        {$walletEarningsQuery} AS wallet,
                                        {$totalEarningsQuery} AS total,
                                        {$adminCommissionQuery} as admin_commision,
                                        {$driverCommissionQuery} as driver_commision,
                                        IFNULL(({$cardEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS card_percentage,
                                        IFNULL(({$cashEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS cash_percentage,
                                        IFNULL(({$walletEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS wallet_percentage
                                    ")
                                    ->companyKey()
                                    ->where('requests.is_completed',true)
                                    ->whereDate('requests.trip_start_time',date('Y-m-d'))
                                    ->get();
        }
        //Overall Earnings
        $overallEarnings = Request::leftJoin('request_bills','requests.id','request_bills.request_id')
                                    ->selectRaw("
                                    {$cardEarningsQuery} AS card,
                                    {$cashEarningsQuery} AS cash,
                                    {$walletEarningsQuery} AS wallet,
                                    {$totalEarningsQuery} AS total,
                                    {$adminCommissionQuery} as admin_commision,
                                    {$driverCommissionQuery} as driver_commision,
                                    IFNULL(({$cardEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS card_percentage,
                                    IFNULL(({$cashEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS cash_percentage,
                                    IFNULL(({$walletEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS wallet_percentage
                                ")
                                ->companyKey()
                                ->where('requests.is_completed',true)
                                ->get();
        if($ownerId != null){
                    $overallEarnings = Request::leftJoin('request_bills','requests.id','request_bills.request_id')
                                    ->selectRaw("
                                    {$cardEarningsQuery} AS card,
                                    {$cashEarningsQuery} AS cash,
                                    {$walletEarningsQuery} AS wallet,
                                    {$totalEarningsQuery} AS total,
                                    {$adminCommissionQuery} as admin_commision,
                                    {$driverCommissionQuery} as driver_commision,
                                    IFNULL(({$cardEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS card_percentage,
                                    IFNULL(({$cashEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS cash_percentage,
                                    IFNULL(({$walletEarningsQuery} / {$totalEarningsQuery}),0) * 100 AS wallet_percentage
                                ")
                                ->companyKey()
                                ->where('requests.is_completed',true)
                                ->where('requests.owner_id',$ownerId)
                                ->get();
        }
        //cancellation chart
             $startDate = Carbon::now()->startOfMonth()->subMonths(6);
             $endDate = Carbon::now();
             $data=[];
        if($ownerId != null){
            while ($startDate->lte($endDate)){

            $from = Carbon::parse($startDate)->startOfMonth();
            $to = Carbon::parse($startDate)->endOfMonth();
            $shortName = $startDate->shortEnglishMonth;
                $monthName = $startDate->monthName;
                $data['cancel'][] = [
                    'y' => $shortName,
                    'a' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','0')->whereIsCancelled(true)->whereOwnerId($ownerId)->count(),
                    'u' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','1')->whereIsCancelled(true)->whereOwnerId($ownerId)->count(),
                    'd' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','2')->whereIsCancelled(true)->whereOwnerId($ownerId)->count()
                ];
                $data['earnings']['months'][] = $monthName;
                $data['earnings']['values'][] = RequestBill::whereHas('requestDetail', function ($query) use ($from,$to,$ownerId) {
                                                            $query->companyKey()->whereBetween('trip_start_time', [$from,$to])->whereIsCompleted(true)->where('owner_id', $ownerId);
                                                        })->sum('total_amount');

                  $startDate->addMonth();
                }

     }else{
        while ($startDate->lte($endDate)){

        $from = Carbon::parse($startDate)->startOfMonth();
        $to = Carbon::parse($startDate)->endOfMonth();
        $shortName = $startDate->shortEnglishMonth;
                $monthName = $startDate->monthName;
                $data['cancel'][] = [
                    'y' => $shortName,
                    'a' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','0')->whereIsCancelled(true)->count(),
                    'u' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','1')->whereIsCancelled(true)->count(),
                    'd' => Request::companyKey()->whereBetween('created_at', [$from,$to])->where('cancel_method','2')->whereIsCancelled(true)->count()
                ];
                $data['earnings']['months'][] = $monthName;
                $data['earnings']['values'][] = RequestBill::whereHas('requestDetail', function ($query) use ($from,$to) {
                                                            $query->companyKey()->whereBetween('trip_start_time', [$from,$to])->whereIsCompleted(true);
                                                        })->sum('total_amount');

                  $startDate->addMonth();
                }
      }

    if (auth()->user()->countryDetail) {
        $currency = auth()->user()->countryDetail->currency_symbol;
    } else {
        $currency = get_settings(Settings::CURRENCY);
    }
    // $currency = auth()->user()->countryDetail->currency_code ?: env('SYSTEM_DEFAULT_CURRENCY');
    $currency = get_settings('currency_code');

    $default_lat = get_settings('default_latitude');
    $default_lng = get_settings('default_longitude');
    // $zone = Zone::active()->companyKey()->get();

    $items = RequestRequest::whereHas('zoneType.zone')->get();
    // dd($results);

    $item = '';
    foreach ($items as $value) {
        $item = $value;
    }

    // return redirect('/airport');
    return view('admin.dashboard', compact('page', 'main_menu','currency', 'sub_menu','total_drivers','total_aproved_drivers','total_waiting_drivers','total_users','trips','todayEarnings','overallEarnings','data','default_lat', 'default_lng','total_admin','total_booking','total_vehicleType','item'));
    }
}
