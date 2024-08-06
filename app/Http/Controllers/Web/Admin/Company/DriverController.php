<?php

namespace App\Http\Controllers\Web\Admin\Company;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Country;
use App\Jobs\NotifyViaMqtt;
use App\Models\Admin\Driver;
use Illuminate\Http\Request;
use App\Jobs\NotifyViaSocket;
use App\Models\Admin\Company;
use App\Models\Master\CarMake;
use App\Models\Master\CarModel;
use App\Base\Constants\Auth\Role;
use App\Models\Admin\VehicleType;
use App\Models\Admin\ServiceLocation;
use App\Http\Controllers\ApiController;
use App\Base\Filters\Admin\DriverFilter;
use App\Base\Constants\Masters\PushEnums;
use App\Models\Admin\DriverNeededDocument;
use App\Http\Controllers\Web\BaseController;
use App\Base\Constants\Auth\Role as RoleSlug;
use App\Transformers\Driver\DriverTransformer;
use App\Base\Filters\Master\CommonMasterFilter;
use App\Jobs\Notifications\AndroidPushNotification;
use App\Base\Libraries\QueryFilter\QueryFilterContract;
use App\Http\Requests\Admin\Driver\CreateDriverRequest;
use App\Http\Requests\Admin\Driver\UpdateDriverRequest;
use App\Base\Services\ImageUploader\ImageUploaderContract;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\OwnerHiredDriver;
use App\Models\Admin\Fleet;
use App\Models\Admin\DriverPrivilegedVehicle;
use Illuminate\Support\Facades\DB;
use App\Jobs\Notifications\SendPushNotification;
use App\Mail\ApprovedDriver;
use App\Mail\Driver\DriverCreateByCompanyMail;
use App\Models\Admin\DriverDetail;
use Illuminate\Support\Facades\Mail;
use App\Models\Admin\DriverVehicleType;

/**
 * @resource Driver
 *
 * vechicle types Apis
 */
class DriverController extends BaseController
{
    /**
     * The Driver model instance.
     *
     * @var \App\Models\Admin\Driver
     */
    protected $driver;
    protected $country;

    /**
     * The User model instance.
     *
     * @var \App\Models\User
     */
    protected $user;

    /**
     * The
     *
     * @var App\Base\Services\ImageUploader\ImageUploaderContract
     */
    protected $imageUploader;


    /**
     * DriverController constructor.
     *
     * @param \App\Models\Admin\Driver $driver
     */
    public function __construct(Driver $driver, ImageUploaderContract $imageUploader, User $user,Country $country)
    {
        $this->driver = $driver;
        $this->imageUploader = $imageUploader;
        $this->user = $user;
        $this->country = $country;
    }

    /**
    * Get all drivers
    * @return \Illuminate\Http\JsonResponse
    */
    public function index()
    {
        $page = trans('pages_names.drivers');
        $main_menu = 'drivers';
        $sub_menu = 'driver_details';

        return view('admin.company-driver.drivers.index', compact('page', 'main_menu', 'sub_menu'));
    }

    /**
    * Fetch all drivers
    */
    public function getAllDrivers(QueryFilterContract $queryFilter)
    {
        // dd(auth()->user()->owner->owner_unique_id);
        $url = request()->fullUrl(); //get full url
        return cache()->tags('drivers_list')->remember($url, Carbon::parse('10 minutes'), function () use ($queryFilter) {
            if (access()->hasRole(RoleSlug::OWNER)) {
                // $query = Driver::whereOwnerId(auth()->user()->owner->id)->orderBy('created_at', 'desc');
                $query = Driver::where('owner_id', auth()->user()->owner->owner_unique_id)
                ->where('owner_id', '!=', null)
                ->orderBy('created_at', 'desc');

                if (env('APP_FOR')=='demo') {
                    $query = Driver::whereHas('user', function ($query) {
                        $query->whereCompanyKey(auth()->user()->company_key);
                    })->orderBy('created_at', 'desc');
                }
            } else {
                $this->validateAdmin();
                $query = $this->driver->where('service_location_id', auth()->user()->admin->service_location_id)->orderBy('created_at', 'desc');
                // $query = Driver::orderBy('created_at', 'desc');
            }
            $results = $queryFilter->builder($query)->customFilter(new DriverFilter)->paginate();

            return view('admin.company-driver.drivers._drivers', compact('results'))->render();
        });
    }

    /**
    * Create Driver View
    *
    */
    public function create()
    {
        $page = trans('pages_names.add_driver');

        // $admins = User::doesNotBelongToRole(RoleSlug::SUPER_ADMIN)->get();
        $services = ServiceLocation::companyKey()->whereActive(true)->get();
        if (access()->hasRole(RoleSlug::SUPER_ADMIN)) {
            $types = VehicleType::whereActive(true)->get();
        } else {
            $types = VehicleType::where('company_key', auth()->user()->company_key)->get();
        }
        // dd($types);
        $countries = Country::all();
        $carmake = CarMake::active()->get();
        $companies = Company::active()->get();

        $main_menu = 'drivers';
        $sub_menu = 'driver_details';

        return view('admin.company-driver.drivers.create', compact('services', 'types', 'page', 'countries', 'main_menu', 'sub_menu', 'companies', 'carmake'));
    }

    /**
     * Create Driver.
     *
     * @param \App\Http\Requests\Admin\Driver\CreateDriverRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CreateDriverRequest $request)
    {
        $created_params = $request->only(['company_key','name', 'driving_license','mobile','email','address','state','city','gender','car_color','car_number','transport_type','approve','vehicle_type','car_make','car_model']);
        // $created_params['owner_id'] = auth()->user()->admin->id;
        $created_params['service_location_id'] = auth()->user()->admin->service_location_id;
        $created_params['postal_code'] = $request->postal_code;
        $created_params['uuid'] = driver_uuid();
        $created_params['company_key'] = auth()->user()->company_key;

        $validate_exists_email = $this->user->belongsTorole(Role::DRIVER)->where('email', $request->email)->exists();

        $validate_exists_mobile = $this->user->belongsTorole(Role::DRIVER)->where('mobile', $request->mobile)->exists();

        if ($validate_exists_email) {
            return redirect()->back()->withErrors(['email'=>'Provided email has already been taken'])->withInput();
        }
        if ($validate_exists_mobile) {
            return redirect()->back()->withErrors(['mobile'=>'Provided mobile has already been taken'])->withInput();
        }

        $service_location = ServiceLocation::find(auth()->user()->admin->service_location_id);

        // $country_id = $service_location->country;
        $country_id =  $this->country->where('dial_code', $request->input('country'))->pluck('id')->first();

        DB::beginTransaction();
        try {
            $user = $this->user->create(['name'=>$request->input('name'),
                'email'=>$request->input('email'),
                'mobile'=>$request->input('mobile'),
                'mobile_confirmed'=>true,
                'password' => bcrypt($request->input('password')),
                'company_key'=>auth()->user()->company_key,
                'refferal_code'=> str_random(6),
                'country'=>$country_id,

            ]);

            if ($uploadedFile = $this->getValidatedUpload('profile', $request)) {
                $created_params['profile'] = $this->imageUploader->file($uploadedFile)
                    ->saveDriverProfilePicture();
            }

            $user->attachRole(RoleSlug::DRIVER);
            $driver = $user->driver()->create($created_params);

            $driver_detail = $driver->driverDetail()->create([
                'is_company_driver' => true
            ]);

            $data = [
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'password' => $request->input('password'),
                'approve' => $request->approve,
                'comapny_name' => auth()->user()->name
            ];

            if ($user->email) {
                Mail::to($user->email)->send(new DriverCreateByCompanyMail($data));
            }

            $message = trans('succes_messages.driver_added_succesfully');

            cache()->tags('drivers_list')->flush();
        } catch (\Throwable $th) {
            DB::rollBack();
            // dd($th);
            return back()->with('warning','Something went wrong!')->withInput();
        }
        DB::commit();

        return redirect('company/drivers')->with('success', $message);
    }

    public function getById(Driver $driver)
    {
        $page = trans('pages_names.edit_driver');

        $services = ServiceLocation::whereActive(true)->get();
        if (access()->hasRole(RoleSlug::SUPER_ADMIN)) {
            $types = VehicleType::whereActive(true)->get();
        } else {
            $types = VehicleType::where('owner_id', auth()->user()->owner->owner_unique_id)->get();
        }
        $countries = Country::all();
        $companies = Company::active()->get();
        $item = $driver;
        $carmake = CarMake::active()->get();
        $carmodel = CarModel::active()->whereMakeId($item->car_make)->get();
        $main_menu = 'drivers';
        $sub_menu = 'driver_details';

        return view('admin.company-driver.drivers.update', compact('item', 'services', 'types', 'page', 'countries', 'main_menu', 'sub_menu', 'companies', 'carmake', 'carmodel'));
        // return view('admin.drivers.update', compact('item', 'services', 'types', 'page', 'countries', 'main_menu', 'sub_menu', 'companies', 'carmake', 'carmodel'));
    }


    public function update(Driver $driver, UpdateDriverRequest $request)
    {
        $updatedParams = $request->only(['name','mobile','email','driving_license','address','state','city','country','gender','car_color','car_number','car_model','car_make','postal_code','vehicle_type']);

        $validate_exists_email = $this->user->belongsTorole(Role::DRIVER)->where('email', $request->email)->where('id', '!=', $driver->user_id)->exists();

        $validate_exists_mobile = $this->user->belongsTorole(Role::DRIVER)->where('mobile', $request->mobile)->where('id', '!=', $driver->user_id)->exists();

        if ($validate_exists_email) {
            return redirect()->back()->withErrors(['email'=>'Provided email hs already been taken'])->withInput();
        }
        if ($validate_exists_mobile) {
            return redirect()->back()->withErrors(['mobile'=>'Provided mobile hs already been taken'])->withInput();
        }



        DB::beginTransaction();
        try {
            $user_param = $request->only(['profile']);

            $user_param['profile']=null;

            if ($uploadedFile = $this->getValidatedUpload('profile_picture', $request)) {
                $user_param['profile'] = $this->imageUploader->file($uploadedFile)
                    ->saveProfilePicture();
            }

            $driver->update(['name'=>$request->input('name'),
                'email'=>$request->input('email'),
                'mobile'=>$request->input('mobile'),
                'transport_type'=>$request->input('transport_type'),
                'car_make'=>$request->input('car_make'),
                'car_model'=>$request->input('car_model'),
                'car_color'=>$request->input('car_color'),
                'car_number'=>$request->input('car_number'),
                // 'vehicle_type'=>$request->input('type'),
                'service_location_id'=>$request->service_location_id,
                'driving_license'=>$request->driving_license

            ]);

            // $driver->update($updatedParams);

            $driver->user()->update([
                'name'=>$request->input('name'),
                'email'=>$request->input('email'),
                'mobile'=>$request->input('mobile'),
                'profile_picture'=>$user_param['profile']
            ]);

            $driverVehicleTypes =  $driver->driverVehicleTypeDetail()->get();

            // dd($driverVehicleTypes);

            foreach ($driverVehicleTypes as $driverVehicleType)
            {
                $driverVehicleType->delete();
            }

            foreach ($request->type as $type)
            {
                DriverVehicleType::create(['driver_id' => $driver->id,
                    'vehicle_type' => $type,]);
            }

            $message = trans('succes_messages.driver_updated_succesfully');
            cache()->tags('drivers_list')->flush();
        } catch (\Throwable $th) {
            DB::rollBack();
            // dd($th);
            return back()->with('warning','Something went wrong!')->withInput();
        }
        DB::commit();

        return redirect('company/drivers')->with('success', $message);
    }

    public function toggleStatus(Driver $driver)
    {
        $status = $driver->active == 1 ? 0 : 1;
        $driver->update([
            'active' => $status
        ]);

        $message = trans('success_messages.driver_status_changed_succesfully');
        return redirect('company/drivers')->with('success', $message);
    }
   public function toggleApprove(Driver $driver)
    {
        $status = $driver->approve == 1 ? 0 : 1;
        // dd($status);

        if ($status) {
            $err = false;
            $neededDoc = DriverNeededDocument::count();
            $uploadedDoc = count($driver->driverDocument);

            if ($neededDoc != $uploadedDoc) {
                // $message = trans('succes_messages.driver_document_not_uploaded');
                return redirect('drivers/document/view/'.$driver->id);
            }

            foreach ($driver->driverDocument as $driverDoc) {
                if ($driverDoc->document_status != 1) {
                    $err = true;
                }
            }

            if ($err) {
                $message = trans('succes_messages.driver_document_not_approved');
                return redirect('company/drivers')->with('warning', $message);
            }
        }

        $driver->update([
            'approve' => $status
        ]);

        $message = trans('succes_messages.driver_approve_status_changed_succesfully');
        $user = User::find($driver->user_id);
        if ($status) {
            $title = trans('push_notifications.driver_approved');
            $body = trans('push_notifications.driver_approved_body');
            $push_data = ['notification_enum'=>PushEnums::DRIVER_ACCOUNT_APPROVED];
        } else {
            $title = trans('push_notifications.driver_declined_title');
            $body = trans('push_notifications.driver_declined_body');
            $push_data = ['notification_enum'=>PushEnums::DRIVER_ACCOUNT_DECLINED];
        }

        dispatch(new SendPushNotification($user,$title,$body));

        return redirect('company/drivers')->with('success', $message);
    }

    public function toggleAvailable(Driver $driver)
    {
        $status = $driver->available == 1 ? 0 : 1;
        $driver->update([
            'available' => $status
        ]);

        $message = trans('success_messages.driver_available_status_changed_succesfully');
        return redirect('company/drivers')->with('success', $message);
    }

    public function delete(Driver $driver)
    {
        $driver->delete();

        $message = trans('succes_messages.driver_deleted_succesfully');
        return $message;

    }

    public function getCarModel()
    {
        $carModel = request()->car_make;

        return CarModel::active()->whereMakeId($carModel)->get();
    }

    public function UpdateDriverDeclineReason(Request $request)
    {
        $driver = Driver::whereId($request->id)->update([
            'reason' => $request->reason
        ]);

        return 'success';
    }

    public function profile(Driver $driver){
        $page = trans('pages_names.driver_profile');

        $item = $driver;
        $main_menu = 'driver_management';
        $sub_menu = 'manage_drivers';

        return view('admin.company-driver.drivers.profileview', compact('item', 'page', 'main_menu', 'sub_menu'));
    }

    public function companyDriver($company){
        return $this->driver->driverDetail()->whereIsCompanyDriver(true)->whereCompany($company);
    }

    public function hireDriverView(){
        $page = trans('pages_names.hire_driver');

        $main_menu = 'driver_management';
        $sub_menu = 'manage_drivers';

        return view('admin.company-driver.drivers.hire', compact('page', 'main_menu', 'sub_menu'));
    }

    public function hireDriver(Request $request){
        Validator::make($request->all(),[
            'driver' => 'required|exists:drivers,uuid'
        ])->validate();

        $uuid = $request->driver;
        $driver = Driver::whereUuid($uuid)->first();


        if(!OwnerHiredDriver::whereDriverId($driver->id)->whereOwnerId(auth()->user()->owner->id)->exists()){
            OwnerHiredDriver::create([
                'driver_id' => $driver->id,
                'owner_id' => auth()->user()->owner->id
            ]);
        }else{
            $message = trans('succes_messages.driver_already_hired');
            return back()->with('warning', $message);
        }

        $driver = Driver::whereUuid($uuid)->update([
            'owner_id' => auth()->user()->owner->id
        ]);


        $message = trans('succes_messages.driver_hired_succesfully');

        return redirect('company/drivers')->with('success', $message);
    }

    public function fleetPrivilegeView(Driver $driver){
        $page = trans('pages_names.privileged_vehicles');

        $main_menu = 'driver_management';
        $sub_menu = 'manage_drivers';
        $fleets = Fleet::active()->whereOwnerId(auth()->user()->id)->get();

        return view('admin.company-driver.drivers.fleet_privilege', compact('page', 'main_menu', 'sub_menu','driver','fleets'));
    }

    public function storePrivilegedVehicle(Request $request,Driver $driver){

        Validator::make($request->all(),[
            'fleets' => 'required'
        ])->validate();

        $driver->privilegedVehicle()->delete();
        foreach($request->fleets as $fleet){
            $driver->privilegedVehicle()->create([
                'fleet_id' => $fleet,
                'owner_id' => auth()->user()->owner->id
            ]);
        }

        $message = trans('succes_messages.vehicle_assigned_to_driver');

        return redirect('company/drivers')->with('success', $message);
    }

    public function unlinkVehicle(Driver $driver,DriverPrivilegedVehicle $vehicle){
        $driver->privilegedVehicle()->whereId($vehicle->id)->delete();

        $message = trans('succes_messages.vehicle_unlink_successfully');

        return back()->with('success', $message);
    }


    public function approveDriver(Driver $driver)
    {
        $driverApprove = Driver::where('id', $driver->id)->first();
        if ($driverApprove) {
            // $driverApprove->approve = !$driverApprove->approve;
            $driverApprove->approve = request()->status;
            $driverApprove->save();
        }

        $data = [
            'name' => $driverApprove->name,
            'email' => $driverApprove->email,
            'approve' => $driverApprove->approve,
        ];

        Mail::to($driverApprove->email)->send(new ApprovedDriver($data));

        if ($driverApprove->approve == 1) {
            $message = trans('succes_messages.driver_approved_succesfully');
        }else{
            $message = trans('succes_messages.driver_disapproved_succesfully');
        }

        return $message;
        // return redirect('admins')->with('success', $message);
    }
}
