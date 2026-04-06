<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactForm;
use App\Models\ContentPage;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * @OA\Tag(
 *     name="Public",
 *     description="Public website utility endpoints — contact, newsletter, booking tracker, FAQs"
 * )
 */
class PublicController extends Controller
{
    /**
     * @OA\Post(
     *     path="/public/contact",
     *     summary="Submit a contact form",
     *     tags={"Public"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","email","message"},
     *         @OA\Property(property="name", type="string", example="John Doe"),
     *         @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *         @OA\Property(property="phone", type="string", example="+1234567890"),
     *         @OA\Property(property="subject", type="string", example="Question about my booking"),
     *         @OA\Property(property="message", type="string", example="I need help with my reservation."),
     *         @OA\Property(property="type", type="string", enum={"general","booking_support","property_issue","billing","other"}, example="general"),
     *         @OA\Property(property="booking_reference", type="string", example="PKG-ABCD1234")
     *     )),
     *     @OA\Response(response=201, description="Message received"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function contact(Request $request)
    {
        // Rate limit: 5 submissions per IP per hour
        $key = 'contact_form_' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many submissions. Please try again later.',
            ], 429);
        }
        RateLimiter::hit($key, 3600);

        $request->validate([
            'name'              => 'required|string|max:100',
            'email'             => 'required|email|max:200',
            'phone'             => 'nullable|string|max:20',
            'subject'           => 'nullable|string|max:200',
            'message'           => 'required|string|min:10|max:2000',
            'type'              => 'nullable|in:general,booking_support,property_issue,billing,other',
            'booking_reference' => 'nullable|string|max:50',
        ]);

        ContactForm::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'subject'           => $request->subject,
            'message'           => $request->message,
            'type'              => $request->type ?? 'general',
            'booking_reference' => $request->booking_reference,
            'ip_address'        => $request->ip(),
            'status'            => 'new',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been received. We will get back to you within 24 hours.',
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/public/newsletter/subscribe",
     *     summary="Subscribe to the newsletter",
     *     tags={"Public"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"email"},
     *         @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *         @OA\Property(property="name", type="string", example="John"),
     *         @OA\Property(property="source", type="string", example="footer")
     *     )),
     *     @OA\Response(response=200, description="Subscribed successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function newsletterSubscribe(Request $request)
    {
        $request->validate([
            'email'  => 'required|email|max:200',
            'name'   => 'nullable|string|max:100',
            'source' => 'nullable|string|max:50',
        ]);

        $subscriber = NewsletterSubscriber::subscribe(
            $request->email,
            $request->name,
            $request->source ?? 'website'
        );

        $message = $subscriber->wasRecentlyCreated
            ? 'Thank you for subscribing to our newsletter!'
            : 'You are already subscribed. Welcome back!';

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * @OA\Get(
     *     path="/public/newsletter/unsubscribe/{token}",
     *     summary="Unsubscribe from newsletter using token",
     *     tags={"Public"},
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Unsubscribed"),
     *     @OA\Response(response=404, description="Invalid token")
     * )
     */
    public function newsletterUnsubscribe($token)
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'Invalid or already unsubscribed.'], 404);
        }

        $subscriber->update([
            'status'           => 'unsubscribed',
            'unsubscribed_at'  => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'You have been unsubscribed.']);
    }

    /**
     * @OA\Get(
     *     path="/public/bookings/{bookingReference}/track",
     *     summary="Track a booking status (no auth required — for confirmation page)",
     *     tags={"Public"},
     *     @OA\Parameter(name="bookingReference", in="path", required=true, @OA\Schema(type="string", example="PKG-ABCD1234")),
     *     @OA\Parameter(name="email", in="query", required=true, description="Email used for booking", @OA\Schema(type="string", format="email")),
     *     @OA\Response(response=200, description="Booking status"),
     *     @OA\Response(response=404, description="Booking not found")
     * )
     */
    public function trackBooking(Request $request, $bookingReference)
    {
        $request->validate(['email' => 'required|email']);

        $booking = Booking::where('booking_reference', $bookingReference)
            ->where('holder_email', $request->email)
            ->first();

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'booking_reference'       => $booking->booking_reference,
                'property_name'           => $booking->property_name,
                'property_city'           => $booking->property_city,
                'check_in_date'           => $booking->check_in_date,
                'check_out_date'          => $booking->check_out_date,
                'nights'                  => $booking->nights,
                'total_adults'            => $booking->total_adults,
                'total_price'             => $booking->total_price,
                'currency'                => $booking->currency,
                'booking_status'          => $booking->booking_status,
                'payment_status'          => $booking->payment_status,
                'holder_first_name'       => $booking->holder_first_name,
                'is_cancellable'          => $booking->isCancellable(),
                'free_cancellation_until' => $booking->free_cancellation_until,
                'confirmed_at'            => $booking->confirmed_at,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/public/faqs",
     *     summary="Get FAQ list",
     *     tags={"Public"},
     *     @OA\Parameter(name="category", in="query", @OA\Schema(type="string", example="booking")),
     *     @OA\Response(response=200, description="FAQ list")
     * )
     */
    public function faqs(Request $request)
    {
        // Try to get from CMS content pages first
        $cms = ContentPage::where('type', 'section')
            ->where('slug', 'faq')
            ->where('is_active', true)
            ->first();

        if ($cms) {
            return response()->json(['success' => true, 'source' => 'cms', 'data' => $cms->content]);
        }

        // Fallback: static FAQ list
        $category = $request->get('category', 'all');

        $faqs = Cache::remember("faqs_{$category}", 3600, fn () => $this->staticFaqs($category));

        return response()->json(['success' => true, 'data' => $faqs]);
    }

    /**
     * @OA\Get(
     *     path="/public/site-info",
     *     summary="Get site meta info — currency list, languages, property types",
     *     tags={"Public"},
     *     @OA\Response(response=200, description="Site meta information")
     * )
     */
    public function siteInfo()
    {
        $info = Cache::remember('site_info', 3600, fn () => [
            'currencies'     => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'SGD', 'AED', 'INR'],
            'languages'      => [
                ['code' => 'en', 'label' => 'English'],
                ['code' => 'es', 'label' => 'Spanish'],
                ['code' => 'fr', 'label' => 'French'],
                ['code' => 'de', 'label' => 'German'],
                ['code' => 'ar', 'label' => 'Arabic'],
            ],
            'property_types' => [
                'house', 'villa', 'apartment', 'condo', 'cabin', 'cottage',
                'bungalow', 'townhouse', 'studio', 'loft', 'resort', 'hotel',
            ],
            'sort_options' => [
                ['value' => 'newest',     'label' => 'Newest First'],
                ['value' => 'price_asc',  'label' => 'Price: Low to High'],
                ['value' => 'price_desc', 'label' => 'Price: High to Low'],
                ['value' => 'rating',     'label' => 'Highest Rated'],
                ['value' => 'popular',    'label' => 'Most Popular'],
            ],
            'guest_limits'   => ['min_adults' => 1, 'max_adults' => 20, 'max_children' => 10],
        ]);

        return response()->json(['success' => true, 'data' => $info]);
    }

    // -------------------------------------------------------
    // Admin: manage contact forms
    // -------------------------------------------------------

    /**
     * @OA\Get(
     *     path="/admin/contact-forms",
     *     summary="List all contact form submissions",
     *     tags={"Admin - Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"new","in_progress","resolved"})),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Contact form list")
     * )
     */
    public function adminListContacts(Request $request)
    {
        $query = ContactForm::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type,   fn ($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc');

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    /**
     * @OA\Put(
     *     path="/admin/contact-forms/{id}/reply",
     *     summary="Reply to a contact form submission",
     *     tags={"Admin - Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"reply"},
     *         @OA\Property(property="reply", type="string")
     *     )),
     *     @OA\Response(response=200, description="Reply sent")
     * )
     */
    public function adminReplyContact(Request $request, $id)
    {
        $form = ContactForm::findOrFail($id);
        $request->validate(['reply' => 'required|string|max:3000']);

        $form->update([
            'admin_reply' => $request->reply,
            'replied_at'  => now(),
            'status'      => 'resolved',
        ]);

        // TODO: Mail::to($form->email)->send(new ContactReplyMail($form));

        return response()->json(['success' => true, 'message' => 'Reply saved.']);
    }

    /**
     * @OA\Get(
     *     path="/admin/newsletter-subscribers",
     *     summary="List newsletter subscribers",
     *     tags={"Admin - Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active","unsubscribed"})),
     *     @OA\Response(response=200, description="Subscriber list")
     * )
     */
    public function adminListSubscribers(Request $request)
    {
        $subscribers = NewsletterSubscriber::query()
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $total  = NewsletterSubscriber::count();
        $active = NewsletterSubscriber::where('status', 'active')->count();

        return response()->json([
            'success' => true,
            'stats'   => ['total' => $total, 'active' => $active, 'unsubscribed' => $total - $active],
            'data'    => $subscribers,
        ]);
    }

    // -------------------------------------------------------
    private function staticFaqs(string $category): array
    {
        $all = [
            [
                'category' => 'booking',
                'question' => 'How do I make a booking?',
                'answer'   => 'Search for a property, select your dates, and complete the booking form. Payment is processed securely via Authorize.Net.',
            ],
            [
                'category' => 'booking',
                'question' => 'Can I book as a guest without registering?',
                'answer'   => 'Yes! You can complete a guest booking without creating an account. You will receive confirmation by email.',
            ],
            [
                'category' => 'cancellation',
                'question' => 'What is the cancellation policy?',
                'answer'   => 'Most properties allow free cancellation up to 7 days before check-in. Review the property page for specific policies.',
            ],
            [
                'category' => 'cancellation',
                'question' => 'How do I cancel my booking?',
                'answer'   => 'Log into your account, go to My Bookings, and select Cancel. Alternatively contact our support team.',
            ],
            [
                'category' => 'payment',
                'question' => 'What payment methods are accepted?',
                'answer'   => 'We accept Visa, Mastercard, American Express, and Discover credit/debit cards.',
            ],
            [
                'category' => 'payment',
                'question' => 'Is my payment information secure?',
                'answer'   => 'Yes. All payments are processed through Authorize.Net, a PCI-DSS compliant payment gateway. We never store your card details.',
            ],
            [
                'category' => 'account',
                'question' => 'How do I reset my password?',
                'answer'   => 'Click "Forgot Password" on the login page and enter your email. You will receive a password reset link.',
            ],
            [
                'category' => 'account',
                'question' => 'How do I become a host?',
                'answer'   => 'Register for a host account and complete your profile. Once verified by our team, your properties will be listed.',
            ],
        ];

        if ($category === 'all') return $all;
        return array_values(array_filter($all, fn ($f) => $f['category'] === $category));
    }
}
