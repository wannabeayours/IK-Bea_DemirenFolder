import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import React, { useEffect, useState } from 'react'
import {
  Form,
  FormField,
  FormItem,
  FormLabel,
  FormControl,
  FormMessage,
} from "@/components/ui/form"
import { useForm } from "react-hook-form"
import { z } from "zod"
import { zodResolver } from "@hookform/resolvers/zod"
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import { Star, Loader2, Info, RefreshCcw, ArrowLeft } from 'lucide-react';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

const schema = z.object({
  review: z.string().min(1, { message: "This field is required" }),
  rating: z.number().min(1, { message: "This is required" }),
})

function CustomerFeedback() {
  const [hasCheckedOut, setHasCheckedOut] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [hoverRating, setHoverRating] = useState(0);
  const [charCount, setCharCount] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [isEditing, setIsEditing] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const ratingLabels = ['Very Poor', 'Poor', 'Okay', 'Good', 'Excellent'];

  const hasCustomerCheckOuted = async () => {
    try {
      const url = localStorage.getItem('url') + "customer.php";
      const customerId = localStorage.getItem("userId");
      const jsonData = {
        customerId: customerId,
      };
      const formData = new FormData();
      formData.append("operation", "hasCustomerCheckOuted");
      formData.append("json", JSON.stringify(jsonData));
      const res = await axios.post(url, formData);
      let value = res.data;
      try {
        value = typeof value === 'string' ? JSON.parse(value) : value;
      } catch (_) {
        // leave as-is if not JSON
      }
      setHasCheckedOut(Number(value) === 1);
      console.log("res ni hasCustomerCheckOuted", res);
    } catch (error) {
      toast.error("Something went wrong");
      console.log(error);
    }
  }

  const getCustomerFeedback = async () => {
    try {
      const url = localStorage.getItem('url') + "customer.php";
      const customerId = localStorage.getItem("userId");
      const jsonData = {
        customerId: customerId,
      };
      const formData = new FormData();
      formData.append("operation", "getCustomerFeedback");
      formData.append("json", JSON.stringify(jsonData));
      const res = await axios.post(url, formData);
      console.log("res ni getCustomerFeedback", res);
      let data = res.data;
      try {
        data = typeof data === 'string' ? JSON.parse(data) : data;
      } catch (_) {
        // keep original if not JSON
      }
      if (Array.isArray(data)) {
        setFeedback(data[0] || null);
      } else if (data && typeof data === 'object') {
        setFeedback(data);
      } else {
        setFeedback(null);
      }
    } catch (error) {
      toast.error("Something went wrong");
      console.log(error);
    }
  }

  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: {
      review: "",
      rating: 0,
    },
  })
  const navigateTo = useNavigate();
  const onSubmit = async (values) => {
    try {
      setIsSubmitting(true);
      const url = localStorage.getItem('url') + "customer.php";
      const CustomerId = localStorage.getItem("userId");
      const jsonData = {
        customers_id: CustomerId,
        customersreviews: values.review,
        rating: values.rating,
      };
      const formData = new FormData();
      formData.append("operation", "customerFeedBack");
      formData.append("json", JSON.stringify(jsonData));
      const res = await axios.post(url, formData);
      console.log("res ni onSubmit", res);
      let value = res.data;
      try {
        value = typeof value === 'string' ? JSON.parse(value) : value;
      } catch (_) {
        // keep original if not JSON
      }
      if (Number(value) === 1 || (value && value.success)) {
        toast.success("Feedback submitted successfully");
        navigateTo("/customer");
      } else {
        toast.error("Unable to submit feedback");
      }
    } catch (error) {
      toast.error("Something went wrong");
      console.error(error);
    } finally {
      setIsSubmitting(false);
    }
  }

  useEffect(() => {
    if (localStorage.getItem("userId")) {
      const fetchAll = async () => {
        await Promise.allSettled([getCustomerFeedback(), hasCustomerCheckOuted()]);
        setIsLoading(false);
      };
      fetchAll();
    } else {
      navigateTo("/login");
    }
  }, [navigateTo])

  const startEditing = () => {
    setIsEditing(true);
    const currentComment = (feedback && feedback.customersreviews_comment) ? feedback.customersreviews_comment : '';
    const currentRating = (feedback && feedback.customersreviews_rating) ? Number(feedback.customersreviews_rating) : 0;
    form.reset({ review: currentComment, rating: currentRating });
    setCharCount(currentComment.length);
  }

  return (
    <div className="flex items-center justify-center flex-col ">
      <Button
        type="button"
        variant="ghost"
        onClick={() => navigateTo('/customer')}
        className="mt-6 flex items-center gap-2 text-indigo-700 hover:text-indigo-800 hover:bg-indigo-50"
      >
        <ArrowLeft className="h-4 w-4" />
        Back
      </Button>
      <Card className={"px-10 mt-20 w-full md:w-1/2 bg-white border rounded-xl shadow-sm"}>
        <CardHeader>
          <CardTitle className="text-2xl font-bold">Share Your Experience</CardTitle>
          <p className="text-sm text-gray-600 mt-1">Your feedback helps us improve our service.</p>
        </CardHeader>
        <CardContent>
          {isLoading ? (
          <div className="flex items-center text-gray-600 text-sm">
            <Loader2 className="mr-2 h-4 w-4 animate-spin" /> Loading...
          </div>
          ) : !hasCheckedOut ? (
          <div className="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
            <Info className="h-4 w-4" />
            <span>You have to check out first before giving feedback.</span>
          </div>
          ) : (
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <FormField
                control={form.control}
                name="review"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Your Review</FormLabel>
                    <FormControl>
                      <div>
                        <Textarea rows={6} maxLength={500} placeholder="Tell us about your stay..." {...field}
                          onChange={(e) => { setCharCount(e.target.value.length); field.onChange(e); }} />
                        <div className="mt-1 text-xs text-gray-500 flex justify-end">{charCount}/500</div>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="rating"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Rating</FormLabel>
                    <FormControl>
                      <div className='w-full'>
                        <div className="flex items-center gap-2">
                          <div className="flex space-x-1" aria-label="Star rating">
                            {[1, 2, 3, 4, 5].map((starValue) => (
                              <Star
                                key={starValue}
                                className={cn(
                                  "h-8 w-8 cursor-pointer transition-colors",
                                  (hoverRating || field.value) >= starValue ? "fill-yellow-400 stroke-yellow-400" : "stroke-gray-300"
                                )}
                                onMouseEnter={() => setHoverRating(starValue)}
                                onMouseLeave={() => setHoverRating(0)}
                                onClick={() => field.onChange(starValue)}
                              />
                            ))}
                          </div>
                          <span className="ml-2 text-sm text-gray-600 min-w-28">
                            {field.value > 0 ? `${field.value}/5 · ${ratingLabels[field.value - 1]}` : 'Select a rating'}
                          </span>
                        </div>
                        <p className="text-xs text-gray-500 mt-1">5 = Excellent</p>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <Separator />
              <div className="flex items-center justify-between">
                <p className="text-xs text-gray-500">Thank you for taking the time to share feedback.</p>
                <Button type="submit" disabled={isSubmitting || form.getValues('rating') === 0}>
                  {isSubmitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />} Submit
                </Button>
              </div>
            </form>
          </Form>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

export default CustomerFeedback