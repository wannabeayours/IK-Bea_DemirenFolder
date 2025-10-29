import * as React from 'react';
import { useForm } from "react-hook-form"
import { useCallback } from 'react';
import { z } from "zod"
import { zodResolver } from "@hookform/resolvers/zod"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage,
} from "@/components/ui/form"
import { Link, useNavigate } from 'react-router-dom';
import { toast } from 'sonner';
import axios from 'axios';
import { Card, CardContent } from '@/components/ui/card';
import { ArrowLeftCircleIcon, HomeIcon } from 'lucide-react';

function Login() {
    const { useEffect, useState } = React;
    const [isCaptchaValid, setIsCaptchaValid] = useState(false);
    const [captchaCharacters, setCaptchaCharacters] = useState([]);
    const [userInput, setUserInput] = useState("");
    const navigateTo = useNavigate();

    // OTP verification states
    const [showOTPModal, setShowOTPModal] = useState(false);
    const [otpInput, setOtpInput] = useState("");
    const [pendingUser, setPendingUser] = useState(null);
    const [pendingOTPCode, setPendingOTPCode] = useState("");

    const getRandomColor = () => {
        const colors = ["red", "blue", "green", "yellow", "purple", "orange"];
        return colors[Math.floor(Math.random() * colors.length)];
    };

    const generateCaptchaCharacters = useCallback(() => {
        const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!@#$%^&*";
        const newCaptcha = [];
        for (let i = 0; i < 5; i++) {
            newCaptcha.push({
                char: characters[Math.floor(Math.random() * characters.length)],
                color: getRandomColor(),
            });
        }
        setCaptchaCharacters(newCaptcha);
        setUserInput(""); // Reset input field
        setIsCaptchaValid(false);
    }, []);

    useEffect(() => {
        generateCaptchaCharacters();
    }, [generateCaptchaCharacters]);

    const handleInputChange = (e) => {
        setUserInput(e.target.value);
        if (e.target.value === captchaCharacters.map((c) => c.char).join("")) {
            setIsCaptchaValid(true);
        } else {
            setIsCaptchaValid(false);
        }
    };



    const schema = z.object({
        email: z.string().min(1, { message: "Please enter username" }),
        password: z.string().min(1, { message: "Password is required" }),
        // captcha: z.string().min(1, { message: "Captcha is required" })
        //     .refine((val) => parseInt(val) === sum, { message: "Incorrect Captcha" })
    })

    const form = useForm({
        resolver: zodResolver(schema),
        defaultValues: {
            email: "",
            password: "",
            // captcha: "" 
        }
    })

    const onSubmit = async (values) => {
        try {
            if (isCaptchaValid === false) {
                toast.error("Invalid CAPTCHA");
                return;
            }
            const url = localStorage.getItem('url') + "customer.php";
            const jsonData = { username: values.email, password: values.password };
            console.log("=== LOGIN ATTEMPT ===");
            console.log("Sending login data:", jsonData);
            console.log("API URL:", url);
            const formData = new FormData();
            formData.append("operation", "login");
            formData.append("json", JSON.stringify(jsonData));
            const res = await axios.post(url, formData);
            console.log("Full API Response:", res);
            console.log("Response Data (raw):", res.data);
            console.log("Response Status:", res.status);

            // Parse the JSON string response
            const responseData = typeof res.data === 'string' ? JSON.parse(res.data) : res.data;
            console.log("Response Data (parsed):", responseData);
            console.log("Success check:", responseData.success);
            console.log("User check:", responseData.user);
            console.log("User type check:", responseData.user_type);

            if (responseData && responseData.success && responseData.user) {
                const user = responseData.user;
                const userType = responseData.user_type;

                console.log("=== LOGIN SUCCESS ===");
                console.log("User Type:", userType);
                console.log("User Data:", user);
                console.log("Requires OTP:", responseData.requires_otp);

                // Check if OTP is required
                if (responseData.requires_otp && responseData.otp_code) {
                    console.log("2FA enabled, requiring OTP verification");
                    setPendingOTPCode(responseData.otp_code);
                    setPendingUser({ user, userType });
                    setShowOTPModal(true);
                    return;
                }

                console.log("=== LOGIN SUCCESS ===");
                console.log("User Type:", userType);
                console.log("User Data:", user);
                toast.success("Successfully logged in as Customer");
                localStorage.setItem("userId", user.customers_id);
                localStorage.setItem("customerOnlineId", user.customers_online_id);
                localStorage.setItem("fname", user.customers_fname);
                localStorage.setItem("lname", user.customers_lname);
                localStorage.setItem("email", user.customers_email);
                localStorage.setItem("contactNumber", user.customers_phone);
                localStorage.setItem("userType", "customer");
                setTimeout(() => {
                    navigateTo("/customer");
                }, 1500);
            }
            else {
                console.log("=== LOGIN FAILED ===");
                console.log("Login failed - Response structure:", responseData);
                console.log("Why login failed - success:", responseData?.success, "user:", responseData?.user);
                if (responseData && responseData.message) {
                    toast.error(responseData.message);
                } else {
                    toast.error("Invalid username or password");
                }
            }

        } catch (error) {
            console.log("=== LOGIN ERROR ===");
            console.log("Network error:", error);
            toast.error("Network error");
        }
    }

    // Handle OTP verification for 2FA
    const handleOTPVerification = () => {
        if (otpInput !== pendingOTPCode) {
            toast.error("Incorrect OTP code");
            return;
        }

        // OTP verified, complete login
        if (pendingUser && pendingUser.userType === "customer") {
            const user = pendingUser.user;
            toast.success("Successfully logged in as Customer");
            localStorage.setItem("userId", user.customers_id);
            localStorage.setItem("customerOnlineId", user.customers_online_id);
            localStorage.setItem("fname", user.customers_fname);
            localStorage.setItem("lname", user.customers_lname);
            localStorage.setItem("userType", "customer");
            setShowOTPModal(false);
            setOtpInput("");
            setPendingUser(null);
            setPendingOTPCode("");
            navigateTo("/customer");
        }
    };

    return (
        <div className="min-h-screen bg-gradient-to-b from-indigo-900 via-indigo-700 to-indigo-800 flex items-center justify-center p-3 sm:p-4 lg:p-6 relative overflow-hidden">
            {/* Enhanced Background Elements */}
            <div className="absolute inset-0 overflow-hidden">
                {/* Animated gradient orbs - responsive sizes */}
                <div className="absolute top-1/4 left-1/4 w-32 h-32 sm:w-48 sm:h-48 lg:w-64 lg:h-64 bg-gradient-to-r from-indigo-400/20 to-indigo-600/20 rounded-full blur-3xl animate-pulse"></div>
                <div className="absolute bottom-1/4 right-1/4 w-40 h-40 sm:w-60 sm:h-60 lg:w-80 lg:h-80 bg-gradient-to-r from-indigo-400/15 to-indigo-600/15 rounded-full blur-3xl animate-pulse delay-1000"></div>
                <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-48 h-48 sm:w-72 sm:h-72 lg:w-96 lg:h-96 bg-gradient-to-r from-indigo-400/10 to-indigo-600/10 rounded-full blur-3xl animate-pulse delay-2000"></div>

                {/* Geometric patterns - responsive sizes */}
                <div className="absolute top-4 right-4 sm:top-6 sm:right-6 lg:top-10 lg:right-10 w-12 h-12 sm:w-16 sm:h-16 lg:w-20 lg:h-20 border border-white/10 rotate-45 animate-spin-slow"></div>
                <div className="absolute bottom-4 left-4 sm:bottom-6 sm:left-6 lg:bottom-10 lg:left-10 w-8 h-8 sm:w-12 sm:h-12 lg:w-16 lg:h-16 border border-white/10 rotate-12 animate-bounce-slow"></div>
                <div className="absolute top-1/3 right-1/3 w-6 h-6 sm:w-8 sm:h-8 lg:w-12 lg:h-12 bg-white/5 rotate-45 animate-pulse"></div>
            </div>

            {/* Centered login card - responsive sizing */}
            <Card className="w-full max-w-xs sm:max-w-sm md:max-w-md bg-white border border-gray-200 shadow-2xl rounded-2xl relative z-10 mx-auto">
                <CardContent className="w-full space-y-4 p-4 sm:p-6">
                    <div className="text-center mb-4 sm:mb-5">
                        <div className="flex items-center justify-start">
                            <Button variant="outline" className="bg-transparent text-gray-700" onClick={() => navigateTo("/")} >
                                <ArrowLeftCircleIcon />
                            </Button>
                        </div>
                        {/* Modern Logo/Icon - responsive sizing */}
                        <div className="mb-3 sm:mb-4 flex justify-center">
                            <div className="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-indigo-400 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                <svg className="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <div className="mb-3">
                            <h1 className="text-xl sm:text-2xl font-bold text-gray-900 mb-1">
                                Welcome Back
                            </h1>
                            <div className="w-12 h-0.5 bg-gradient-to-r from-indigo-400 to-indigo-600 mx-auto mt-2 rounded-full"></div>
                        </div>
                        <p className="text-xs sm:text-sm text-gray-600">
                            Please sign in to your account
                        </p>
                    </div>

                    {/* Enhanced Form */}
                    <Form {...form}>
                        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
                            {/* Enhanced Email Field */}
                            <FormField
                                control={form.control}
                                name="email"
                                render={({ field }) => (
                                    <FormItem className="space-y-1.5">
                                        <FormLabel className="text-sm font-medium text-black/90">Email / Username</FormLabel>
                                        <FormControl>
                                            <div className="relative">
                                                <Input
                                                    placeholder="Enter your email or username"
                                                    className="h-9 px-3 py-2 text-sm rounded-lg bg-white/10 border-2 border-black/20 text-black placeholder:text-black/60 focus:border-indigo-300 focus:ring-indigo-300/30 transition-all duration-300"
                                                    {...field}
                                                />
                                                <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                                                    <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </FormControl>
                                        <FormMessage className="text-xs" />
                                    </FormItem>
                                )}
                            />

                            {/* Enhanced Password Field */}
                            <FormField
                                control={form.control}
                                name="password"
                                render={({ field }) => (
                                    <FormItem className="space-y-1.5">
                                        <div className="flex justify-between items-center">
                                            <FormLabel className="text-sm font-medium text-black/90">Password</FormLabel>
                                        </div>
                                        <FormControl>
                                            <div className="relative">
                                                <Input
                                                    type="password"
                                                    placeholder="Enter your password"
                                                    className="h-9 px-3 py-2 text-sm rounded-lg bg-white/10 border-2 border-black/20 text-black placeholder:text-black/60 focus:border-indigo-300 focus:ring-indigo-300/30 transition-all duration-300"
                                                    {...field}
                                                />
                                                <div className="absolute inset-y-0 right-0 flex items-center pr-3">
                                                    <svg className="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                </div>
                                            </div>
                                        </FormControl>
                                        <div className="flex justify-end">
                                            <Button variant="link" asChild className="h-auto p-0 text-xs text-indigo-600 hover:text-indigo-700 transition-colors">
                                                <Link to="/forgot-password">Forgot Password?</Link>
                                            </Button>
                                        </div>
                                        <FormMessage className="text-xs" />
                                    </FormItem>
                                )}
                            />

                            {/* Enhanced Captcha */}
                            <div className="bg-gray-50 rounded-xl p-4 border border-gray-200 shadow-inner">
                                <h2 className="text-sm font-bold mb-3 text-gray-800 text-center flex items-center justify-center gap-2">
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                    Security Verification
                                </h2>
                                <div className="bg-gray-100 rounded-lg p-3 shadow-sm border-2 border-gray-200 mb-3">
                                    <div className="flex justify-center items-center gap-2">
                                        {captchaCharacters.map((c, index) => (
                                            <span
                                                key={index}
                                                style={{
                                                    color: c.color,
                                                    fontSize: "20px",
                                                    fontWeight: "bold",
                                                    textShadow: "1px 1px 2px rgba(0,0,0,0.3)",
                                                    transform: `rotate(${Math.random() * 15 - 7.5}deg)`
                                                }}
                                                className="select-none"
                                            >
                                                {c.char}
                                            </span>
                                        ))}
                                    </div>
                                </div>

                                <Input
                                    type="text"
                                    value={userInput}
                                    onChange={handleInputChange}
                                    placeholder="Enter the characters above"
                                    className="border border-gray-300 p-2 w-full rounded-lg text-center h-9 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all duration-300 bg-white shadow-sm text-gray-900 placeholder:text-gray-400"
                                />

                                <div className="flex justify-center mt-2">
                                    <Button
                                        type="button"
                                        variant="link"
                                        onClick={generateCaptchaCharacters}
                                        className="text-indigo-600 hover:text-indigo-700 text-xs underline h-auto p-0 transition-colors"
                                    >
                                        🔄 Generate New Code
                                    </Button>
                                </div>

                                {!isCaptchaValid && userInput.length > 0 && (
                                    <div className="mt-2 p-2 bg-red-50 border border-red-200 rounded-lg">
                                        <p className="text-red-700 text-xs text-center font-medium">
                                            ❌ Incorrect verification code, please try again.
                                        </p>
                                    </div>
                                )}

                                {isCaptchaValid && (
                                    <div className="mt-2 p-2 bg-green-50 border border-green-200 rounded-lg">
                                        <p className="text-green-700 text-xs text-center font-medium">
                                            ✅ Verification successful!
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Sign In Button */}
                            <Button
                                type="submit"
                                disabled={!isCaptchaValid}
                                className={`w-full h-12 rounded-xl font-semibold text-base transition-all duration-300 transform hover:scale-[1.02] ${isCaptchaValid
                                    ? 'bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white shadow-lg hover:shadow-xl'
                                    : 'bg-slate-300 text-slate-500 cursor-not-allowed'
                                    }`}
                            >
                                Sign In
                            </Button>

                            {/* Sign Up Section */}
                            <div className="text-center pt-4 border-t border-gray-200">
                                <p className="text-gray-700 text-sm">
                                    Don't have an account?{' '}
                                    <Link
                                        to="/register"
                                        className="text-indigo-600 hover:text-indigo-700 font-semibold transition-colors duration-200 hover:underline"
                                    >
                                        Sign up here
                                    </Link>
                                </p>
                            </div>
                        </form>
                    </Form>
                </CardContent>
            </Card>

            {/* OTP Verification Modal for 2FA */}
            {showOTPModal && (
                <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                    <Card className="w-full max-w-md bg-white shadow-2xl">
                        <CardContent className="p-6 space-y-4">
                            <div className="text-center">
                                <h2 className="text-xl font-bold text-gray-800 mb-2">Enter Verification Code</h2>
                                <p className="text-sm text-gray-600">
                                    We've sent a verification code to your email. Please enter it below.
                                </p>
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-700 mb-2 block">OTP Code</label>
                                    <input
                                        type="text"
                                        value={otpInput}
                                        onChange={(e) => setOtpInput(e.target.value)}
                                        placeholder="Enter 6-digit code"
                                        maxLength={6}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-center text-lg tracking-widest"
                                        autoFocus
                                    />
                                </div>

                                <div className="flex gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setShowOTPModal(false);
                                            setOtpInput("");
                                            setPendingUser(null);
                                            setPendingOTPCode("");
                                        }}
                                        className="flex-1"
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={handleOTPVerification}
                                        disabled={!otpInput || otpInput.length !== 6}
                                        className="flex-1 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white"
                                    >
                                        Verify & Login
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            )}
        </div>

    )
}



export default Login