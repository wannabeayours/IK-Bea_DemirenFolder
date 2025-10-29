import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import axios from 'axios';
import React, { useEffect, useState } from 'react'
import { toast } from 'sonner';

function Authentication() {

    const [status, setStatus] = useState(false);
    
    // 2FA OTP states
    const [is2FAOTPModalOpen, setIs2FAOTPModalOpen] = useState(false);
    const [otp2FAInput, setOtp2FAInput] = useState('');
    const [otp2FASent, setOtp2FASent] = useState(false);
    const [otp2FASending, setOtp2FASending] = useState(false);
    const [pending2FAToggle, setPending2FAToggle] = useState(null);

    const getCustomerAuthenticationStatus = async () => {
        try {
            const url = localStorage.getItem('url') + "customer.php";
            const customerOnlineId = localStorage.getItem("customerOnlineId");
            const jsonData = { "customers_online_id": customerOnlineId }
            const formData = new FormData();
            formData.append("json", JSON.stringify(jsonData));
            formData.append("operation", "getCustomerAuthenticationStatus")
            const res = await axios.post(url, formData)
            console.log("getCustomerAuthenticationStatus res", res);
            setStatus(Number(res.data) === 1 ? true : false);

        } catch (error) {
            toast.error("Something went wrong");
            console.error(error);

        }
    }
    
    // SHA256 hashing function
    const sha256Hex = async (data) => {
        const msgBuffer = new TextEncoder().encode(data);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    };

    // Send OTP for 2FA toggle verification
    const send2FAOTP = async () => {
        if (otp2FASending) return;
        
            const url = localStorage.getItem('url') + "customer.php";
            const customerOnlineId = localStorage.getItem("customerOnlineId");
        
        // Get customer email
        const jsonData = { "customers_online_id": customerOnlineId }
        const formData1 = new FormData();
        formData1.append("json", JSON.stringify(jsonData));
        formData1.append("operation", "customerGetEmail");
        
        let email = '';
        try {
            const res = await axios.post(url, formData1);
            email = res.data?.customers_online_email || '';
        } catch (e) {
            toast.error('Failed to get email');
            return;
        }
        
        if (!email || !email.includes('@')) {
            toast.error('Valid email is required to send OTP');
            return;
        }

        // Generate a secure 6-character OTP (alphanumeric)
        const chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // avoid ambiguous chars
        const buf = new Uint32Array(6);
        crypto.getRandomValues(buf);
        let otp = '';
        for (let i = 0; i < buf.length; i++) otp += chars[buf[i] % chars.length];

        const salt = (customerOnlineId || '') + '|DEMIREN_CUSTOMER_2FA_TOGGLE_OTP_SALT_v1';
        const hash = await sha256Hex(`${otp}|${email}|${salt}`);

        // Store encrypted OTP (hash) and expiry
        sessionStorage.setItem('customer_2fa_toggle_otp_hash', hash);
        sessionStorage.setItem('customer_2fa_toggle_otp_email', email);
        sessionStorage.setItem('customer_2fa_toggle_otp_expiry', String(Date.now() + 300000)); // 5 minutes

        // Send email via backend
        try {
            setOtp2FASending(true);
            const form = new FormData();
            form.append('operation', 'sendCustomerOTP');
            form.append('json', JSON.stringify({ email, otp_code: otp }));
            
            const res = await axios.post(url, form);
            
            if (res?.data?.success) {
                toast.success('OTP sent to your email');
                setOtp2FASent(true);
            } else {
                toast.error(res?.data?.message || 'Failed to send OTP');
            }
        } catch (e) {
            console.error('Error sending OTP:', e);
            toast.error('Error sending OTP');
        } finally {
            setOtp2FASending(false);
        }
    };

    // Verify OTP for 2FA toggle
    const verify2FAOTP = async () => {
        const storedHash = sessionStorage.getItem('customer_2fa_toggle_otp_hash');
        const storedEmail = sessionStorage.getItem('customer_2fa_toggle_otp_email');
        const expiryStr = sessionStorage.getItem('customer_2fa_toggle_otp_expiry');
        
        if (!storedHash || !storedEmail || !expiryStr) {
            toast.error('Please click "Send OTP" and enter the code');
            return false;
        }
        
        const expiry = parseInt(expiryStr, 10);
        const now = Date.now();
        
        if (now > expiry) {
            toast.error('OTP expired. Please send a new code');
            return false;
        }
        
        const salt = (localStorage.getItem('customerOnlineId') || '') + '|DEMIREN_CUSTOMER_2FA_TOGGLE_OTP_SALT_v1';
        const inputHash = await sha256Hex(`${otp2FAInput}|${storedEmail}|${salt}`);
        
        if (inputHash !== storedHash) {
            toast.error('Incorrect OTP');
            return false;
        }
        
        return true;
    };

    // Handle 2FA toggle - requires OTP verification
    const handleToggle2FA = async (next) => {
        const customerOnlineId = localStorage.getItem("customerOnlineId");
        if (!customerOnlineId) {
            toast.error('Customer access required');
            return;
        }

        // Store the pending toggle value and open OTP modal
        setPending2FAToggle(next);
        setIs2FAOTPModalOpen(true);
        setOtp2FAInput('');
        setOtp2FASent(false);
        // Clear previous OTP session
        sessionStorage.removeItem('customer_2fa_toggle_otp_hash');
        sessionStorage.removeItem('customer_2fa_toggle_otp_email');
        sessionStorage.removeItem('customer_2fa_toggle_otp_expiry');
    };

    // Actually perform the 2FA toggle after OTP verification
    const confirmToggle2FA = async () => {
        if (!otp2FASent) {
            toast.error('Please send OTP first');
            return;
        }

        if (!otp2FAInput) {
            toast.error('Please enter the OTP code');
            return;
        }

        const verified = await verify2FAOTP();
        if (!verified) {
            return;
        }

        // Proceed with the actual toggle
        try {
            const url = localStorage.getItem('url') + "customer.php";
            const customerOnlineId = localStorage.getItem("customerOnlineId");
            const formData = new FormData();
            formData.append('operation', 'customerChangeAuthenticationStatus');
            formData.append('json', JSON.stringify({
                customers_online_id: customerOnlineId,
                customer_online_authentication_status: pending2FAToggle ? 1 : 0,
            }));

            const response = await axios.post(url, formData);
            const data = response.data;

            if (data === 1) {
                setStatus(pending2FAToggle);
                setIs2FAOTPModalOpen(false);
                setOtp2FAInput('');
                setOtp2FASent(false);
                setPending2FAToggle(null);
                toast.success('2FA setting updated successfully');
                // Clear OTP session
                sessionStorage.removeItem('customer_2fa_toggle_otp_hash');
                sessionStorage.removeItem('customer_2fa_toggle_otp_email');
                sessionStorage.removeItem('customer_2fa_toggle_otp_expiry');
            } else {
                toast.error('Failed to update 2FA setting');
            }
        } catch (error) {
            console.error('Update 2FA setting error:', error);
            toast.error('Error updating 2FA setting');
        }
    };

    // Auto-send OTP when 2FA modal opens
    useEffect(() => {
        if (is2FAOTPModalOpen && !otp2FASent && !otp2FASending) {
            send2FAOTP();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [is2FAOTPModalOpen]);

    useEffect(() => {
        getCustomerAuthenticationStatus();
    }, []);

    return (

        <>
        <Dialog>
            <DialogTrigger>
                <Button className={"bg-gradient-to-r from-blue-900 to-indigo-700 hover:from-blue-700 hover:to-indigo-700 text-white "}>
                    Two-Factor Authentication
                </Button>
            </DialogTrigger>
            <DialogContent>
                <div className='text-lg font-bold flex justify-center'>
                    Two-Factor Authentication
                </div>
                <div className="flex items-center justify-between space-x-2 ">
                    <Label >Enable Two-Factor Authentication</Label>
                    <Switch checked={status} onCheckedChange={handleToggle2FA} />
                </div>
            </DialogContent>
        </Dialog>

        {/* 2FA OTP Verification Modal */}
        <Dialog open={is2FAOTPModalOpen} onOpenChange={setIs2FAOTPModalOpen}>
            <DialogContent>
                <div className='text-lg font-bold flex justify-center mb-4'>
                    {pending2FAToggle ? 'Activate' : 'Deactivate'} Two-Factor Authentication
                </div>
                
                {!otp2FASent ? (
                    <div className="text-center py-4">
                        <p className="text-sm text-gray-600 mb-2">Sending OTP to your email...</p>
                        {otp2FASending && (
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-[#113f67] mx-auto"></div>
                        )}
                    </div>
                ) : (
                    <>
                        <p className="text-sm text-gray-600 text-center mb-4">
                            We've sent a verification code to your email. Please enter it below.
                        </p>
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="otp-input">Enter OTP Code</Label>
                                <Input
                                    id="otp-input"
                                    type="text"
                                    placeholder="Enter 6-digit code"
                                    value={otp2FAInput}
                                    onChange={(e) => setOtp2FAInput(e.target.value)}
                                    maxLength={6}
                                    className="text-center text-lg tracking-widest"
                                    autoFocus
                                />
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    onClick={() => {
                                        setIs2FAOTPModalOpen(false);
                                        setOtp2FASent(false);
                                        setOtp2FAInput('');
                                        setPending2FAToggle(null);
                                    }}
                                    variant="outline"
                                    className="flex-1"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={confirmToggle2FA}
                                    className="flex-1"
                                    disabled={!otp2FAInput}
                                >
                                    Verify & {pending2FAToggle ? 'Activate' : 'Deactivate'}
                                </Button>
                            </div>
                            <div className="text-center">
                                <Button
                                    variant="link"
                                    onClick={send2FAOTP}
                                    disabled={otp2FASending}
                                    className="text-sm text-[#113f67]"
                                >
                                    Resend OTP
                                </Button>
                            </div>
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
        </>
    )
}

export default Authentication