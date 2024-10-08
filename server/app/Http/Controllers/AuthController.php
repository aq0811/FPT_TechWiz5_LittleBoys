<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendVerificationCode;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'login',
            'register',
            'sendVerificationCode',
            'checkVerificationCode',
            'resetPassword'
        ]]);
    }

    protected function respondWithToken($token)
    {
        $msg = new \stdClass();
        $msg->en = "You have logged in successfully!";
        $msg->vi = "Bạn đã đăng nhập thành công!";
        return response()->json([
            'success' => true,
            'message' => [$msg],
            'token' => $token,
            'type' => 'bearer',
            'expIn' => auth()->factory()->getTTL() * 60
        ], 200);
    }

    public function login()
    {
        $message = [];
        $email = request('email');
        $password = request('password');

        if(empty($email) || empty($password)){
            $msg = new \stdClass();
            $msg->en = "All fields are required!";
            $msg->vi = "Tất cả các trường đều bắt buộc!";
            array_push($message, $msg);
        } else {
            $credentials = request(['email', 'password']);

            if (!$token = auth()->attempt($credentials)) {
                $msg = new \stdClass();
                $msg->en = "Email or password is incorrect!";
                $msg->vi = "Email hoặc mật khẩu không đúng!";
                array_push($message, $msg);
            }
        }

        if(count($message) > 0){
            return response()->json([
                'success' => false,
                'message' => $message
            ], 401);
        }

        return $this->respondWithToken($token);
    }

    public function fetchData()
    {
        return response()->json([
            'success' => true,
            'user' => auth()->user()
        ], 200);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function register(Request $req){
        $error = [];
        $fullname = $req -> fullname;
        $email = $req -> email;
        $password = $req -> password;
        $confirmPassword = $req -> confirmPassword;
        $phone = $req -> phone;
        $gender = $req -> gender;
        $address = $req -> address;

        if(empty($fullname) || empty($email) || empty($password) || empty($confirmPassword) || empty($phone) || empty($gender) || empty($address)){
            $msg = new \stdClass();
            $msg->en = "All fields are required!";
            $msg->vi = "Tất cả các trường đều bắt buộc!";
            array_push($error, $msg);
        } else {
            $regexFullname = "/^(?! )[a-zA-Z\s\u{0080}-\u{FFFF}]{2,50}(?<! )$/u";
            $regexEmail = "/^[a-z0-9]+@[a-z0-9-]+(?:\.[a-z0-9-]+)*$/";
            $regexPassword = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()_+-])[a-zA-Z0-9!@#$%^&*()_+-]{8,30}$/";
            $regexPhone = "/^0[9|8|1|7|3|5]([-. ]?[0-9]{7,9})$/";

            if(!preg_match($regexFullname, $fullname)){
                $msg = new \stdClass();
                $msg->en = "The name must be from 2 to 50 characters long. Numbers and special characters are not allowed.";
                $msg->vi = "Tên phải dài từ 2 đến 50 ký tự. Không được phép sử dụng số và ký tự đặc biệt.";
                array_push($error, $msg);
            }

            if(!preg_match($regexEmail, $email)){
                $msg = new \stdClass();
                $msg->en = "Invalid email format.";
                $msg->vi = "Email không đúng định dạng.";
                array_push($error, $msg);
            } else {
                $checkEmail = User::where('email', $email)->first();
                if($checkEmail){
                    $msg = new \stdClass();
                    $msg->en = "Email already exists.";
                    $msg->vi = "Email đã tồn tại.";
                    array_push($error, $msg);
                }
            }

            if(!preg_match($regexPassword, $password)){
                $msg = new \stdClass();
                $msg->en = "The password must be from 8 to 30 characters long. It must include one uppercase letter, one number, and one special character from the following list: ['!', '@', '#', ''', ''', '%', '^', '&', '*', '(', ')', '_', '+', '-']";
                $msg->vi = "Mật khẩu phải dài từ 8 đến 30 ký tự. Mật khẩu phải bao gồm một chữ cái viết hoa, một số và một ký tự đặc biệt từ danh sách sau: ['!', '@', '#', ''', ''', '%', '^', '&', '*', '(', ')', '_', '+', '-']";
                array_push($error, $msg);
            } else {
                if($password != $confirmPassword){
                    $msg = new \stdClass();
                    $msg->en = "Confirm password and password are not the same!";
                    $msg->vi = "Xác nhận mật khẩu và mật khẩu không giống nhau!";
                    array_push($error, $msg);
                }
            }

            if(!preg_match($regexPhone, $phone)){
                $msg = new \stdClass();
                $msg->en = "Invalid phone number format. It must start with 09, 08, 01, 07, 03, or 05 and be followed by 7 to 9 digits.";
                $msg->vi = "Định dạng số điện thoại không hợp lệ. Số phải bắt đầu bằng 09, 08, 01, 07, 03 hoặc 05 và theo sau là 7 đến 9 chữ số.";
                array_push($error, $msg);
            } else {
                $checkPhone = User::where('phone', $phone)->first();
                if($checkPhone){
                    $msg = new \stdClass();
                    $msg->en = "Phone number already exists.";
                    $msg->vi = "Số điện thoại đã tồn tại.";
                    array_push($error, $msg);
                }
            }
        }

        if(count($error) > 0){
            return response()->json([
                'success' => false,
                'message' => $error
            ], 400);
        } else {
            $user = new User;
            $user->fullname = $fullname;
            $user->email = $email;
            $user->password = Hash::make($password);
            $user->phone = $phone;
            $user->gender = $gender;
            $user->address = $address;
            $user->save();

            $msg = new \stdClass();
            $msg->en = "You have successfully registered, please login to continue!";
            $msg->vi = "Bạn đã đăng kí thành công, vui lòng đăng nhập để tiếp tục!";

            return response()->json([
                'success' => true,
                'message' => [$msg]
            ], 200);
        }
    }

    public function sendVerificationCode(Request $req)
    {
        $error = [];
        $email = $req -> email;

        if(empty($email)){
            $msg = new \stdClass();
            $msg->en = "Email is required!";
            $msg->vi = "Email là bắt buộc!";
            array_push($error, $msg);
        } else {
            $checkEmail = User::where('email', $email)->first();
            if(!$checkEmail){
                $msg = new \stdClass();
                $msg->en = "Email does not exist!";
                $msg->vi = "Email không tồn tại!";
                array_push($error, $msg);
            }
        }

        if(count($error) > 0){
            return response()->json([
               'success' => false,
               'message' => $error
            ], 400);
        } else {
            $code = rand(100000, 999999);
            $exp = time() + 5 * 60;

            User::where('email', $email)
            -> update([
                'verificationCode' => $code,
                'verificationCodeExp' => $exp
            ]);

            $data = [
                "verificationCode" => $code
            ];

            Mail::to($email)->send(new SendVerificationCode($data));

            $msg = new \stdClass();
            $msg->en = "Verification code has been sent to your email.";
            $msg->vi = "Mã xác nhận đã được gửi đến email của bạn.";

            return response()->json([
               'success' => true,
               'exp' => $exp,
               'message' => [$msg]
            ], 200);
        }
    }

    public function checkVerificationCode(Request $req){
        $error = [];
        $email = $req -> email;
        $code = $req -> verificationCode;

        if(empty($email) || empty($code)){
            $msg = new \stdClass();
            $msg->en = "Verification code is required!";
            $msg->vi = "Mã xác nhận là bắt buộc!";
            array_push($error, $msg);
        } else {
            $checkEmail = User::where('email', $email)->first();
            if(!$checkEmail){
                $msg = new \stdClass();
                $msg->en = "Email does not exist!";
                $msg->vi = "Email không tồn tại!";
                array_push($error, $msg);
            } else {
                $exp = $checkEmail->verificationCodeExp;
                if($exp < time()){
                    $msg = new \stdClass();
                    $msg->en = "Verification code has expired!";
                    $msg->vi = "Mã xác nhận đã hết hạn!";
                    array_push($error, $msg);
                } else {
                    if($checkEmail->verificationCode!= $code){
                        $msg = new \stdClass();
                        $msg->en = "Verification code is incorrect!";
                        $msg->vi = "Mã xác nhận không đúng!";
                        array_push($error, $msg);
                    }
                }
            }
        }

        if(count($error) > 0){
            return response()->json([
               'success' => false,
               'message' => $error
            ], 400);
        } else {
            $msg = new \stdClass();
            $msg->vi = "Mã xác nhận chính xác! Vui lòng tiến hành thay đổi mật khẩu.";
            $msg->en = "Verification code is correct! Please change your password.";

            return response()->json([
               'success' => true,
               'message' => [$msg]
            ], 200);
        }
    }

    public function resetPassword(Request $req)
    {
        $error = [];
        $email = $req -> email;
        $code = $req -> verificationCode;
        $password = $req -> password;
        $confirmPassword = $req -> confirmPassword;

        if(empty($email) || empty($code) || empty($password) || empty($confirmPassword)){
            $msg = new \stdClass();
            $msg->en = "All fields are required!";
            $msg->vi = "Tất cả các trường đều bắt buộc!";
            array_push($error, $msg);
        } else {
            $checkEmail = User::where('email', $email)->first();
            if(!$checkEmail){
                $msg = new \stdClass();
                $msg->en = "Email does not exist!";
                $msg->vi = "Email không tồn tại!";
                array_push($error, $msg);
            } else {
                if($checkEmail->verificationCode!= $code){
                    $msg = new \stdClass();
                    $msg->en = "Verification code is incorrect!";
                    $msg->vi = "Mã xác nhận không đúng!";
                    array_push($error, $msg);
                } else {
                    $regexPassword = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*[!@#$%^&*()_+-])[a-zA-Z0-9!@#$%^&*()_+-]{8,30}$/";
                    if(!preg_match($regexPassword, $password)){
                        $msg = new \stdClass();
                        $msg->en = "The password must be from 8 to 30 characters long. It must include one uppercase letter, one number, and one special character from the following list: ['!', '@', '#', ''', ''', '%', '^', '&', '*', '(', ')', '_', '+', '-']";
                        $msg->vi = "Mật khẩu phải dài từ 8 đến 30 ký tự. Mật khẩu phải bao gồm một chữ cái viết hoa, một số và một ký tự đặc biệt từ danh sách sau: ['!', '@', '#', ''', ''', '%', '^', '&', '*', '(', ')', '_', '+', '-']";
                        array_push($error, $msg);
                    } else {
                        if($password != $confirmPassword){
                            $msg = new \stdClass();
                            $msg->en = "Confirm password and password are not the same!";
                            $msg->vi = "Xác nhận mật khẩu và mật khẩu không giống nhau!";
                            array_push($error, $msg);
                        } else {
                            if(Hash::check($password, $checkEmail->password)){
                                $msg = new \stdClass();
                                $msg->vi = "Bạn không thể đặt mật khẩu giống như mật khẩu cũ!";
                                $msg->en = "You cannot set a new password that is the same as the old password!";
                                array_push($error, $msg);
                            }
                        }
                    }
                }
            }
        }

        if(count($error) > 0){
            return response()->json([
               'success' => false,
               'message' => $error
            ], 400);
        } else {
            User::where('email', $email)
            -> update([
                'password' => Hash::make($password),
                'verificationCode' => null,
                'verificationCodeExp' => null
            ]);

            $msg = new \stdClass();
            $msg->en = "Password has been reset successfully!";
            $msg->vi = "Mật khẩu đã được đặt lại thành công!";

            return response()->json([
               'success' => true,
               'message' => [$msg]
            ], 200);
        }
    }
}
