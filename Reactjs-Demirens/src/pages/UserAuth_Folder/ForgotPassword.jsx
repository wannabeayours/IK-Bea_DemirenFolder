import React, { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { Card, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { toast } from "sonner";
import axios from "axios";
import { ArrowLeft, Mail } from "lucide-react";

const ForgotPassword = () => {
  const [email, setEmail] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  // OTP session storage keys and validity (5 minutes)
  const RESET_OTP_HASH_KEY = "reset_otp_hash";
  const RESET_OTP_EMAIL_KEY = "reset_otp_email";
  const RESET_OTP_EXPIRY_KEY = "reset_otp_expiry";
  const RESET_OTP_VALIDITY_MS = 5 * 60 * 1000;

  const sha256Hex = async (text) => {
    const enc = new TextEncoder();
    const data = enc.encode(text);
    const hash = await crypto.subtle.digest("SHA-256", data);
    const hashArray = Array.from(new Uint8Array(hash));
    return hashArray.map((b) => b.toString(16).padStart(2, "0")).join("");
  };

  const generateOtpCode = () => {
    // 6-digit numeric OTP
    const buf = new Uint32Array(6);
    crypto.getRandomValues(buf);
    let otp = "";
    for (let i = 0; i < buf.length; i++) otp += String(buf[i] % 10);
    return otp;
  };

  const clearOtpSession = () => {
    sessionStorage.removeItem(RESET_OTP_HASH_KEY);
    sessionStorage.removeItem(RESET_OTP_EMAIL_KEY);
    sessionStorage.removeItem(RESET_OTP_EXPIRY_KEY);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!email.trim()) {
      toast.error("Please enter your email address");
      return;
    }
    
    if (!email.includes("@")) {
      toast.error("Please enter a valid email address");
      return;
    }

    setLoading(true);
    
    try {
      const url = localStorage.getItem("url") + "customer.php";

      // Generate OTP on client, store encrypted/hash+meta in session, send to backend for email
      const otp = generateOtpCode();
      const salt = email.trim() + "|RESET_OTP_SALT_v1";
      const hash = await sha256Hex(`${otp}|${salt}`);
      const expiry = Date.now() + RESET_OTP_VALIDITY_MS;
      sessionStorage.setItem(RESET_OTP_HASH_KEY, hash);
      sessionStorage.setItem(RESET_OTP_EMAIL_KEY, email.trim());
      sessionStorage.setItem(RESET_OTP_EXPIRY_KEY, String(expiry));

      const form = new FormData();
      // Backend should verify email exists in tbl_customers_online and send OTP
      form.append("operation", "checkAndSendOTP");
      form.append(
        "json",
        JSON.stringify({ guest_email: email.trim(), otp_code: otp })
      );

      console.log("Sending OTP for password reset:", { email: email.trim() });
      const res = await axios.post(url, form);

      const data = typeof res.data === "string" ? JSON.parse(res.data) : res.data;
      if (data?.success) {
        toast.success("OTP sent to your email! Please check your inbox.");
        navigate("/reset-password", { state: { email: email.trim() } });
      } else {
        clearOtpSession();
        toast.error(data?.message || "Failed to send OTP. Please try again.");
      }
    } catch (error) {
      toast.error("Something went wrong. Please try again.");
      console.error("Forgot password error:", error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex h-screen bg-gradient-to-br from-[#f7fbfc] to-[#eaf0f6]">
      {/* Left side (placeholder / image / gradient) */}
      <div className="hidden md:flex w-1/2 items-center justify-center bg-[#769FCD]">
        <div className="text-center text-white">
          <Mail className="w-16 h-16 mx-auto mb-4" />
          <h1 className="text-4xl font-bold mb-2">Reset Password</h1>
          <p className="text-lg opacity-90">We'll help you get back into your account</p>
        </div>
      </div>

      {/* Right side - form */}
      <div className="flex w-full md:w-1/2 items-center justify-center p-6">
        <Card className="w-full max-w-md p-8 rounded-2xl shadow-xl bg-white">
          <div className="mb-6">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => navigate("/login")}
              className="mb-4 p-0 h-auto text-[#769FCD] hover:text-[#5578a6]"
            >
              <ArrowLeft className="w-4 h-4 mr-2" />
              Back to Login
            </Button>
            <CardTitle className="text-2xl font-bold text-[#769FCD] mb-2">
              Forgot Password?
            </CardTitle>
            <p className="text-muted-foreground text-sm">
              No worries! Enter your email address and we'll send you an OTP to reset your password.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-6">
            {/* Email Input */}
            <div>
              <label className="block text-sm font-medium mb-2">Email Address</label>
              <Input
                type="email"
                placeholder="Enter your email address"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full"
                required
              />
            </div>

            {/* Send OTP Button */}
            <Button
              type="submit"
              className="w-full bg-[#769FCD] hover:bg-[#5578a6] text-white font-semibold py-2 rounded-lg shadow"
              disabled={loading}
            >
              {loading ? "Sending OTP..." : "Send OTP"}
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

export default ForgotPassword;
