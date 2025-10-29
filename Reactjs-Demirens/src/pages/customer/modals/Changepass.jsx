import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogTrigger } from '@/components/ui/dialog'
import React, { useState } from 'react'
import { useForm } from "react-hook-form"
import { z } from "zod"
import { zodResolver } from "@hookform/resolvers/zod"
import { Input } from "@/components/ui/input"
import {
    Form,
    FormField,
    FormItem,
    FormLabel,
    FormControl,
    FormMessage
} from "@/components/ui/form"
import { cn } from '@/lib/utils'
import axios from 'axios'
import { toast } from 'sonner'
import { Card, CardContent } from '@/components/ui/card'

const schema = z.object({
    oldpass: z.string().min(1, { message: "Old password is required" }),
    newpass: z.string().min(1, { message: "New password is required" }),
    confirmpass: z.string().min(1, { message: "Confirm password is required" }),
})


function Changepass() {

    const form = useForm({
        resolver: zodResolver(schema),
        defaultValues: {
            oldpass: "",
            newpass: "",
            confirmpass: "",
        },
    })

    // OTP states
    const [showOTPModal, setShowOTPModal] = useState(false);
    const [showPasswordDialog, setShowPasswordDialog] = useState(false);
    const [otpInput, setOtpInput] = useState('');
    const [pendingPasswordData, setPendingPasswordData] = useState(null);
    const [otpCode, setOtpCode] = useState('');
    const [isSendingOTP, setIsSendingOTP] = useState(false);

    // Send OTP for password change
    const sendOTP = async () => {
        try {
            setIsSendingOTP(true);
            const url = localStorage.getItem('url') + "customer.php";
            const customerOnlineId = localStorage.getItem("customerOnlineId");
            const formData = new FormData();
            formData.append("operation", "sendPasswordChangeOTP");
            formData.append("json", JSON.stringify({ customers_online_id: customerOnlineId }));
            
            const res = await axios.post(url, formData);
            const data = typeof res.data === 'string' ? JSON.parse(res.data) : res.data;
            
            if (data.success) {
                setOtpCode(data.otp_code);
                toast.success("Verification code sent to your email");
                setIsSendingOTP(false); // Hide loading spinner and show input
            } else {
                toast.error(data.message || "Failed to send verification code");
                setIsSendingOTP(false);
            }
        } catch (error) {
            toast.error("Something went wrong");
            console.error(error);
            setIsSendingOTP(false);
        }
    };

    // Handle form submission - now with OTP verification
    const onSubmit = async (values) => {
        if (values.newpass !== values.confirmpass) {
            toast.error("New password and confirm password does not match");
            return
        }
        
        // Store password data, close password dialog, and show OTP modal
        setPendingPasswordData(values);
        setShowPasswordDialog(false); // Close password dialog
        setShowOTPModal(true); // Show OTP modal
        sendOTP();
    }

    // Verify OTP and change password
    const handleOTPVerification = async () => {
        if (otpInput !== otpCode) {
            toast.error("Incorrect verification code");
            return;
        }

        // OTP verified, proceed with password change
        try {
            const url = localStorage.getItem('url') + "customer.php";
            const customerOnlineId = localStorage.getItem("customerOnlineId");
            const formData = new FormData();
            const jsonData = {
                "customers_online_id": customerOnlineId,
                "current_password": pendingPasswordData.oldpass,
                "new_password": pendingPasswordData.newpass,
            }
            formData.append("operation", "customerChangePassword");
            formData.append("json", JSON.stringify(jsonData));
            const res = await axios.post(url, formData);
            console.log("res ni onSubmit", res);
            if (res.data === -1 || res.data === -2) {
                toast.error("Current password is incorrect");
                // Close OTP modal and reopen password dialog on error
                setShowOTPModal(false);
                setShowPasswordDialog(true);
            }else if(res.data === 1){
                toast.success("Password changed successfully");
                setShowOTPModal(false);
                setOtpInput('');
                setPendingPasswordData(null);
                setOtpCode('');
                setIsSendingOTP(false);
                form.reset();
                // Don't reopen password dialog on success
            } else {
                toast.error("Failed to change password");
                // Close OTP modal and reopen password dialog on error
                setShowOTPModal(false);
                setShowPasswordDialog(true);
            }
        } catch (error) {
            toast.error("Something went wrong");
            console.error(error);
        }
    }
    return (
        <>
        <Dialog open={showPasswordDialog} onOpenChange={setShowPasswordDialog}>
            <DialogTrigger>
                <Button className={"bg-gradient-to-r from-blue-900 to-indigo-700 hover:from-blue-700 hover:to-indigo-700 text-white "}>
                    Change Password
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form {...form}>
                    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
                        <div className='text-lg font-bold flex justify-center'>
                            Change Password
                        </div>



                        <FormField
                           
                            control={form.control}
                            name="oldpass"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Old Password:</FormLabel>
                                    <FormControl>
                                        <Input type="password" placeholder="Enter old password" {...field} />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />


                        <FormField
                            control={form.control}
                            name="newpass"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>New Password:</FormLabel>
                                    <FormControl>
                                        <Input  type="password" placeholder="Enter new password" {...field} />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />

                        <FormField
                            control={form.control}
                            name="confirmpass"
                            render={({ field }) => (
                                <FormItem>
                                    <FormLabel>Confirm new Password:</FormLabel>
                                    <FormControl>
                                        <Input  type="password" placeholder="Confirm new password" {...field} />
                                    </FormControl>
                                    <FormMessage />
                                </FormItem>
                            )}
                        />
                        <div>
                            <div className="flex justify-end">
                                <Button 
                                    type="button"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        const values = form.getValues();
                                        if (values.newpass !== values.confirmpass) {
                                            toast.error("New password and confirm password does not match");
                                            return;
                                        }
                                        onSubmit(values);
                                    }}
                                >
                                    Send Verification Code
                                </Button>
                            </div>

                        </div>

                    </form>
                </Form>
            </DialogContent>
        </Dialog>

        {/* OTP Verification Modal - Separate from password dialog */}
        {showOTPModal && (
            <div className="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 p-4">
                <Card className="w-full max-w-md bg-white shadow-2xl">
                    <CardContent className="p-6 space-y-4">
                        <div className="text-center">
                            <h2 className="text-xl font-bold text-gray-800 mb-2">Verify Password Change</h2>
                            <p className="text-sm text-gray-600">
                                {isSendingOTP ? 'Sending verification code to your email...' : 'We\'ve sent a verification code to your email. Please enter it below.'}
                            </p>
                            {isSendingOTP && (
                                <div className="mt-4 flex justify-center">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#113f67]"></div>
                                </div>
                            )}
                        </div>
                        
                        {!isSendingOTP && (
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-gray-700 mb-2 block">Verification Code</label>
                                    <Input
                                        type="text"
                                        value={otpInput}
                                        onChange={(e) => setOtpInput(e.target.value)}
                                        placeholder="Enter 6-digit code"
                                        maxLength={6}
                                        className="text-center text-lg tracking-widest"
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
                                                setPendingPasswordData(null);
                                                setOtpCode("");
                                                setIsSendingOTP(false);
                                                // Reopen password dialog on cancel
                                                setShowPasswordDialog(true);
                                            }}
                                            className="flex-1"
                                        >
                                            Cancel
                                        </Button>
                                    <Button
                                        type="button"
                                        onClick={handleOTPVerification}
                                        disabled={!otpInput || otpInput.length !== 6}
                                        className="flex-1 bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white"
                                    >
                                        Verify & Change Password
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        )}
        </>
    )
}

export default Changepass