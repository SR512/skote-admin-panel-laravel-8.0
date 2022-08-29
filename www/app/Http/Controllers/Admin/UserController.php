<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserRequest;
use App\Mail\UserCreateNotification;
use App\Models\Categories;
use App\Models\City;
use App\Models\Language;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $params = [];
        $params['query_str'] = $request->query_str;
        $params['role'] = $request->role;
        $params['page'] = $request->page ?? 0;
        $table = resolve('user-repo')->renderHtmlTable($params);

        $skip_roles = [config('constants.SUPER_ADMIN')];
        $roles = Role::whereNotIn('name', $skip_roles)->pluck('name', 'id');
        return view('admin.usermanagement.user_list', compact('table', 'roles'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        $data = [];
        try {
            $skip_roles = [config('constants.SUPER_ADMIN')];
            $roles = Role::whereNotIn('name', $skip_roles)->pluck('name', 'id');

            $data['error'] = false;
            $data['view'] = view('admin.usermanagement.offcanvas', compact('roles'))->render();
            return response()->json($data);

        } catch (\Exception $e) {
            $data['error'] = true;
            $data['message'] = $e->getMessage();
        }
        return response()->json($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request)
    {
        $data = $params = [];
        DB::beginTransaction();
        try {
            // Create user
            $params['role'] = $request->role;
            $params['name'] = $request->name;
            $params['email'] = $request->email;
            $params['password'] = Hash::make($request->password);

            $user = resolve('user-repo')->create($params);

            if (!empty($user)) {

                // Send Mail Username and Password
                $params = [];
                $params['user'] = $user->name;
                $params['email'] = $request->email;
                $params['password'] = $request->password;

                //Mail::send(new UserCreateNotification($params));

                $params = [];
                $params['page'] = $request->page ?? 0;
                $data['error'] = false;
                $data['message'] = 'User create successfully.';
                $data['view'] = resolve('user-repo')->renderHtmlTable($params);

                DB::commit();
                return response()->json($data);

            }

            $data['error'] = true;
            $data['message'] = 'User not created successfully..!';
            return response()->json($data);

        } catch (\Exception $e) {
            DB::rollBack();
            $data['error'] = true;
            $data['message'] = $e->getMessage();
            return response()->json($data);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data = [];
        try {
            $user = resolve('user-repo')->findByID($id);
            $skip_roles = [config('constants.SUPER_ADMIN')];
            $roles = Role::whereNotIn('name', $skip_roles)->pluck('name', 'id');

            $data['error'] = false;
            $data['view'] = view('admin.usermanagement.offcanvas', compact('roles', 'user'))->render();
            return response()->json($data);

        } catch (\Exception $e) {
            $data['error'] = true;
            $data['message'] = $e->getMessage();
        }
        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $data = $params = [];
        DB::beginTransaction();
        try {

            // Update user
            $params = [];
            $params['role'] = $request->role;
            $params['name'] = $request->name;
            $params['email'] = $request->email;

            $user = resolve('user-repo')->update($params, $id);

            if (!empty($user)) {

                $params = [];
                $params['page'] = $request->page ?? 0;
                $data['error'] = false;
                $data['message'] = 'User update successfully.';
                $data['view'] = resolve('user-repo')->renderHtmlTable($params);

                DB::commit();
                return response()->json($data);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $data['error'] = true;
            $data['message'] = $e->getMessage();
            return response()->json($data);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $user = resolve('user-repo')->findById($id);
            if (!empty($user)) {

                $user->delete();
                toastr()->success($user->name . ' deleted successfully..!');
                return redirect()->route('usermanagement.index');
            } else {
                toastr()->error('User not found.!');
            }
        } catch (\Exception $e) {
            toastr()->error($e->getMessage());
            return redirect()->back();
        }
    }

    public function changeStatus($id)
    {
        try {
            $user = resolve('user-repo')->changeStatus($id);
            toastr()->success('Status changed successfully..!');
            return redirect()->route('usermanagement.index');
        } catch (\Exception $e) {
            toastr()->error($e->getMessage());
            return redirect()->back();
        }
    }

    // Change Password

    public function changePassword(ChangePasswordRequest $request)
    {
        try {
            $params = [];
            $params['password'] = Hash::make($request->password);
            $user = resolve('user-repo')->changePassword($params, auth()->user()->id);
            if ($user) {
                toastr()->success('Password changed successfully..!');
            } else {
                toastr()->error('Password not changed successfully..!');

            }
        } catch (\Exception $e) {
            toastr()->error($e->getMessage());
        }
        return redirect()->back();
    }
}
