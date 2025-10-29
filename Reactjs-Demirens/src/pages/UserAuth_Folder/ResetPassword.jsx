import React, { useState, useEffect } from "react";
import { useLocation, useNavigate, Link } from "react-router-dom";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import axios from "axios";
import { ArrowLeft, Eye, EyeOff, Lock } from "lucide-react";

const ResetPassword = () => {
  const [otp, setOtp] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [resendLoading, setResendLoading] = useState(false);
  const [resendCooldown, setResendCooldown] = useState(60);
  const location = useLocation();
  const navigate = useNavigate();
  const email = location.state?.email;

  // SHA-256 hashing utility
  const sha256Hex = async (text) => {
    const encoder = new TextEncoder();
    const data = encoder.encode(text);
    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  };

  // Generate alphanumeric OTP (6 characters)
  const generateOTP = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let otp = '';
    for (let i = 0; i < 6; i++) {
      otp += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return otp;
  };

  // Set up cooldown timer
  useEffect(() => {
    const interval = setInterval(() => {
      setResendCooldown((prev) => (prev > 0 ? prev - 1 : 0));
    }, 1000);
    return () => clearInterval(interval);
  }, [resendCooldown]);

  // Redirect if no email is provided
  useEffect(() => {
    if (!email) {
      toast.error("No email provided. Please start the password reset process again.");
      navigate("/forgot-password");
    }
  }, [email, navigate]);

  const handleOtpChange = (e) => {
    // Only allow alphanumeric characters, convert to uppercase, max 6 characters
    const val = e.target.value.replace(/[^A-Za-z0-9]/g, "").toUpperCase().slice(0, 6);
    setOtp(val);
  };

  const validateForm = () => {
    if (otp.length !== 6) {
      toast.error("Please enter a 6-character OTP code.");
      return false;
    }
    if (newPassword.length < 6) {
      toast.error("Password must be at least 6 characters long.");
      return false;
    }
    if (newPassword !== confirmPassword) {
      toast.error("Passwords do not match.");
      return false;
    }
    return true;
  };

  // Verify OTP on frontend
  const verifyOTP = async (inputOtp) => {
    const storedHash = sessionStorage.getItem('customer_forgot_otp_hash');
    const storedEmail = sessionStorage.getItem('customer_forgot_email');
    const storedExpiry = sessionStorage.getItem('customer_forgot_otp_expiry');

    if (!storedHash || !storedEmail || !storedExpiry) {
      return { valid: false, message: 'OTP session expired. Please request a new OTP.' };
    }

    const now = new Date().getTime();
    const expiry = parseInt(storedExpiry);

    if (now > expiry) {
      return { valid: false, message: 'OTP expired. Please request a new OTP.' };
    }

    if (storedEmail !== email) {
      return { valid: false, message: 'Email mismatch. Please start over.' };
    }

    const inputHash = await sha256Hex(inputOtp.toUpperCase());
    
    if (inputHash !== storedHash) {
      return { valid: false, message: 'Invalid OTP code. Please try again.' };
    }

    return { valid: true };
  };

  const handleResendOTP = async () => {
    if (resendCooldown > 0) return;
    
    setResendLoading(true);
    try {
      const otp_code = generateOTP();
      const otpHash = await sha256Hex(otp_code);
      
      const expiry = new Date().getTime() + (5 * 60 * 1000);
      sessionStorage.setItem('customer_forgot_otp_hash', otpHash);
      sessionStorage.setItem('customer_forgot_email', email);
      sessionStorage.setItem('customer_forgot_otp_expiry', expiry.toString());
      
      const url = localStorage.getItem("url") + "customer.php";
      const otpForm = new FormData();
      otpForm.append("operation", "sendCustomerForgotPasswordOTP");
      otpForm.append("json", JSON.stringify({ 
        email: email,
        otp_code: otp_code 
      }));
      
      const res = await axios.post(url, otpForm);
      
      if (res.data?.success) {
        toast.success("New OTP sent to your email!");
        setResendCooldown(60);
        setOtp("");
      } else {
        toast.error(res.data?.message || "Failed to resend OTP. Please try again.");
      }
    } catch (error) {
      toast.error("Something went wrong. Please try again.");
      console.error("Resend OTP error:", error);
    } finally {
      setResendLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    // Verify OTP on frontend
    const otpVerification = await verifyOTP(otp);
    if (!otpVerification.valid) {
      toast.error(otpVerification.message);
      return;
    }
    
    setLoading(true);
    
    try {
      const url = localStorage.getItem("url") + "customer.php";
      
      const resetForm = new FormData();
      resetForm.append("operation", "resetCustomerPassword");
      resetForm.append("json", JSON.stringify({
        email: email,
        new_password: newPassword
      }));
      
      const res = await axios.post(url, resetForm);
      
      if (res.data?.success) {
        toast.success("Password reset successfully! You can now login with your new password.");
        // Clear OTP data from sessionStorage
        sessionStorage.removeItem('customer_forgot_otp_hash');
        sessionStorage.removeItem('customer_forgot_email');
        sessionStorage.removeItem('customer_forgot_otp_expiry');
        navigate("/login");
      } else {
        toast.error(res.data?.message || "Failed to reset password. Please try again.");
      }
    } catch (error) {
      toast.error("Something went wrong. Please try again.");
      console.error("Reset password error:", error);
    } finally {
      setLoading(false);
    }
  };

  if (!email) {
    return null; // Will redirect in useEffect
  }

  return (
    <div className="flex h-screen bg-gradient-to-br from-[#f7fbfc] to-[#eaf0f6]">
      {/* Left side (placeholder / image / gradient) */}
      <div className="hidden md:flex w-1/2 items-center justify-center bg-[#769FCD]">
        <div className="text-center text-white">
          <Lock className="w-16 h-16 mx-auto mb-4" />
          <h1 className="text-4xl font-bold mb-2">Reset Password</h1>
          <p className="text-lg opacity-90">Enter your OTP and new password</p>
        </div>
      </div>

      {/* Right side - form */}
      <div className="flex w-full md:w-1/2 items-center justify-center p-6">
        <Card className="w-full max-w-md p-8 rounded-2xl shadow-xl bg-white">
          <div className="mb-6">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate("/forgot-password")}
              className="mb-4 p-0 h-auto text-[#769FCD] hover:text-[#5578a6]"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back
            </Button>
            <CardTitle className="text-2xl font-bold text-[#769FCD] mb-2">
              Reset Password
            </CardTitle>
            <p className="text-muted-foreground text-sm">
              Enter the 6-character OTP sent to <strong>{email}</strong> and your new password.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* OTP Input */}
            <div>
              <label className="block text-sm font-medium mb-2">OTP Code</label>
              <Input
                type="text"
                maxLength={6}
                value={otp}
                onChange={handleOtpChange}
                placeholder="Enter 6-character OTP"
                className="text-center tracking-widest text-lg uppercase"
                autoFocus
              />
            </div>
            {/* Resend OTP Button */}
            <div className="flex justify-end">
              <Button
                type="button"
                variant="link"
                onClick={handleResendOTP}
                disabled={resendCooldown > 0 || resendLoading}
                className="text-xs"
              >
                {resendLoading ? "Sending..." : resendCooldown > 0 ? `Resend OTP (${resendCooldown}s)` : "Resend OTP"}
              </Button>
            </div>

            {/* New Password */}
            <div>
              <label className="block text-sm font-medium mb-2">New Password</label>
              <div className="relative">
                <Input
                  type={showPassword ? "text" : "password"}
                  placeholder="Enter new password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  className="w-full pr-10"
                />
                <button
                  type="button"
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  onClick={() => setShowPassword((prev) => !prev)}
                  tabIndex={-1}
                >
                  {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
              <p className="text-xs text-muted-foreground mt-1">
                * At least 6 characters
              </p>
            </div>

            {/* Confirm Password */}
            <div>
              <label className="block text-sm font-medium mb-2">Confirm New Password</label>
              <div className="relative">
                <Input
                  type={showConfirmPassword ? "text" : "password"}
                  placeholder="Confirm new password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full pr-10"
                />
                <button
                  type="button"
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  onClick={() => setShowConfirmPassword((prev) => !prev)}
                  tabIndex={-1}
                >
                  {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
              </div>
            </div>

            {/* Reset Password Button */}
            <Button
              type="submit"
              className="w-full bg-[#769FCD] hover:bg-[#5578a6] text-white font-semibold py-2 rounded-lg shadow"
              disabled={loading}
            >
              {loading ? "Resetting Password..." : "Reset Password"}
            </Button>

            {/* Back to Login */}
            <div className="text-center">
              <p className="text-sm text-muted-foreground">
                Remember your password?{" "}
                <Link
                  to="/login"
                  className="underline underline-offset-4 text-[#769FCD] hover:text-[#5578a6]"
                >
                  Back to Login
                </Link>
              </p>
            </div>
          </form>
        </Card>
      </div>
    </div>
  );
};

export default ResetPassword;
