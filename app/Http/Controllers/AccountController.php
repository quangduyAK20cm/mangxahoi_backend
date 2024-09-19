<?php

namespace App\Http\Controllers;

use App\Http\Requests\SignUpRequest;
use App\Mail\ActiveMail;
use App\Models\Admin;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AccountController extends Controller
{
    public function resentMail(Request $request)
    {
        $user = Client::where('email', $request->email)
            ->first();
        if ($user) {
            $user->update([
                'hash_active' => mt_rand(100000, 999999)
            ]);
            $dataMail['code'] = $user->hash_active;
            $dataMail['fullname'] = $user->fullname;
            Mail::to($request->email)->send(new ActiveMail($dataMail));
            return response()->json([
                'status'    => 1,
                'message'   => 'Email đã gửi thành công',
            ]);
        } else {
            return response()->json([
                'status'    => 0,
                'message'   => 'Email không tồn tại',
            ]);
        }
    }
    public function deleteActive(Request $request)
    {

        Client::where('email', $request->email)->update([
            'hash_active' => null
        ]);
        return response()->json([
            'status'    => 1,
            'message'   => 'Deleted hash active successfully',
        ]);
    }
    public function activeMail(Request $request)
    {
        $hash_active = $request->hash_active;
        $user_hash_active = Client::where('hash_active', $hash_active)->first();

        if ($user_hash_active) {
            if ($user_hash_active->is_active == 0) {
                $user_hash_active->is_active = 1;
                $user_hash_active->save();
                if ($user_hash_active) {
                    $user_hash_active->update([
                        'hash_active' => null
                    ]);
                }
                return response()->json([
                    'status'  => 1,
                    'message' => "Tài khoản của bạn đã được kích hoạt thành công",
                ]);
            } else {
                return response()->json([
                    'status'  => 0,
                    'message' => "Tài khoản đã được kích hoạt",
                ]);
            }
        } else {
            return response()->json([
                'status'  => 0,
                'message' => "Mã xác nhận không hợp lệ. Vui lòng thử lại",
            ]);
        }
    }
    public function register(SignUpRequest $request)
    {
        if ($request->gender == 0) {
            $avata = "avatar_female.jpg";
        } else if ($request->gender == 1) {
            $avata = "avatar_male.jpg";
        } else {
            $avata = "avatar_other.jpg";
        }
        $randomSixDigits = mt_rand(100000, 999999);

        $user = Client::create([
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'fullname' => $request->fullname,
            'nickname' => $request->username,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'avatar' => $avata,
            'hash_active' => $randomSixDigits,
        ]);
        $dataMail['code']          =   $randomSixDigits;
        $dataMail['fullname']      =   $request->fullname;
        Mail::to($request->email)->send(new ActiveMail($dataMail));
        if ($user) {
            return response()->json([
                'status'    => 1,
                'message'    => "Tài khoản của bạn đã được tạo thành công!",
            ]);
        } else {
            return response()->json([
                'status'    => 0,
                'message'    => "Fail",
            ]);
        }
    }

    public function login(Request $request)
    {
        $user = Client::where('username', $request->username)
            ->orWhere('email', $request->username)
            ->first();
        if ($user && Hash::check($request->password, $user->password)) {
            if ($user->status == Client::banned_account) {
                return response()->json([
                    'status'    => -2,
                    'message'   => 'Tài khoản của bạn đã bị cấm',
                ]);
            } else {
                if ($user->is_active == 1) {
                    if (!isset($request->remember) || $request->remember == false) {
                        Auth::guard('client')->login($user);
                    } else {
                        Auth::guard('client')->login($user, true);
                    }
                    $authenticatedUser = Auth::guard('client')->user();
                    $tokens = $authenticatedUser->tokens;
                    $limit = 4;
                    if ($tokens->count() >= $limit) {
                        // Giữ lại giới hạn số lượng token
                        $tokens->sortByDesc('created_at')->slice($limit)->each(function ($token) {
                            $token->delete();
                        });
                    }
                    $token = $authenticatedUser->createToken('authToken', ['*'], now()->addDays(7));

                    return response()->json([
                        'status' => 1,
                        'token' => $token->plainTextToken,
                        'type_token' => 'Bearer',
                    ]);
                } else {
                    if ($user->hash_active == null) {
                        $user->hash_active = mt_rand(100000, 999999);
                        $user->save();
                        $dataMail['code'] = $user->hash_active;
                        $dataMail['fullname'] = $user->fullname;
                        Mail::to($user->email)->send(new ActiveMail($dataMail));
                    }
                    return response()->json([
                        'status' => -1,
                        'email' => $user->email,
                        'message' => 'Tài khoản của bạn chưa được kích hoạt',
                    ]);
                }
            }
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Thông tin đăng nhập không hợp lệ',
            ]);
        }
    }

    public function authorization(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token is missing'], 401);
        }

        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            $currentToken = $user->currentAccessToken();

            if ($user instanceof Client) {
                // Người dùng là client
                return response()->json([
                    'message' => 'Client authenticated.',
                    'user_type' => 'client',
                ]);
            } else if ($user instanceof Admin) {
                // Người dùng là admin
                return response()->json([
                    'message' => 'Admin authenticated.',
                    'user_type' => 'admin',
                ]);
            }
            return response()->json([
                'status'    => $currentToken
            ]);
        }

        return response()->json([
            'message' => 'Token is invalid',
            'status' => false
        ], 200);
    }
}
