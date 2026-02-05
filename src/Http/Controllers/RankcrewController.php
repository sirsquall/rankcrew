<?php

namespace RankCrew\Laravel\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RankCrew\Laravel\Models\BlogPost;
use RankCrew\Laravel\Models\BlogCategory;
use RankCrew\Laravel\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class RankcrewController extends Controller
{
    // 1) /rankcrew/login
    public function login(Request $request)
    {
        if ($request->method() !== 'POST') {
            return response()->json(['error' => 'Only POST allowed on /rankcrew/login.'], 405);
        }

        // Attempt to parse credentials
        $username = '';
        $password = '';

        // 1) form data
        if ($request->has('name') && $request->has('pass')) {
            $username = trim($request->input('name'));
            $password = trim($request->input('pass'));
        }
        // 2) Basic Auth
        elseif ($request->server('PHP_AUTH_USER')) {
            $username = $request->server('PHP_AUTH_USER');
            $password = $request->server('PHP_AUTH_PW');
        }
        // 3) JSON
        else {
            $data = $request->json()->all();
            if (!empty($data['name']) && !empty($data['pass'])) {
                $username = trim($data['name']);
                $password = trim($data['pass']);
            }
        }

        if (!$username || !$password) {
            return response()->json(['error' => 'Missing "name" or "pass".'], 400);
        }

        // Use Laravel's built-in auth
        if (!Auth::attempt(['email' => $username, 'password' => $password])) {
            return response()->json(['error' => 'Invalid credentials.'], 403);
        }

        $user = Auth::user();

        // store a session indicator
        session(['rankcrew_uid' => $user->id]);

        return response()->json([
            'uid' => $user->id,
            'name' => $user->name ?? $user->email,
            'message' => 'Login successful (RankCrew SaaS).'
        ]);
    }

    // 2) /session/token
    public function token(Request $request)
    {
        if ($request->method() !== 'GET') {
            return response()->json(['error' => 'Only GET allowed on /session/token.'], 405);
        }

        if (!session('rankcrew_uid')) {
            return response()->json(['error' => 'Not logged in (no valid session).'], 403);
        }

        if (!session()->has('rankcrew_csrf')) {
            session(['rankcrew_csrf' => Str::random(32)]);
        }

        return response(session('rankcrew_csrf'), 200, ['Content-Type' => 'text/plain']);
    }

    // 3) /api/rankcrew - Create blog post
    public function create(Request $request)
    {
        if ($request->method() !== 'POST') {
            return response()->json(['error' => 'Only POST allowed on /api/rankcrew.'], 405);
        }

        if (!session('rankcrew_uid')) {
            return response()->json(['error' => 'User not logged in (missing or invalid session?).'], 403);
        }

        $csrfHeader = $request->header('X-CSRF-Token', '');
        if (!session()->has('rankcrew_csrf') || $csrfHeader !== session('rankcrew_csrf')) {
            return response()->json(['error' => 'Missing or invalid CSRF token in X-CSRF-Token header.'], 403);
        }

        $payload = $request->json()->all();
        if (empty($payload['content_type']) || empty($payload['data'])) {
            return response()->json(['error' => 'Invalid JSON. Must include "content_type" and "data".'], 400);
        }

        $all_lang_data = $payload['data'];
        $category_id = $payload['category_id'] ?? null;
        // Read is_published from payload, default to true
        $is_published = isset($payload['is_published']) ? (bool) $payload['is_published'] : true;

        if (empty($category_id)) {
            $defaultCategory = BlogCategory::firstOrCreate(
                ['slug' => 'general'],
                ['name' => 'General']
            );
            $category_id = $defaultCategory->id;
        }

        $created_posts = [];
        $translation_group_id = Str::uuid();

        // Loop through all languages and create a post for each
        foreach ($all_lang_data as $language_code => $lang_data) {
            // Extract data for this language
            $title = $lang_data['title'] ?? 'Untitled';
            $body = $lang_data['body'] ?? '';

            // Extract Teaser Logic
            $teaser = '';

            // Attempt to find the first sentence/paragraph (HTML)
            $firstSentenceHtml = '';
            if (preg_match('/<p>.*?<\/p>/s', $body, $matches)) {
                $firstSentenceHtml = $matches[0];
            }

            if (!empty($firstSentenceHtml)) {
                // Remove the first sentence from body
                $body = $this->removeFirstSentenceSimple($body, $firstSentenceHtml);

                // Strip all tags from teaser to get plain text
                $teaser = strip_tags($firstSentenceHtml);
                $teaser = html_entity_decode($teaser, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $teaser = trim($teaser);
            }

            // Handle image if provided
            $image_url = null;
            if (
                !empty($lang_data['image'])
                && !empty($lang_data['image']['image_base64'])
                && !empty($lang_data['image']['mime_type'])
            ) {
                try {
                    $image_url = $this->storeBase64Image(
                        $lang_data['image']['image_base64'],
                        $lang_data['image']['mime_type']
                    );
                } catch (\Exception $e) {
                    \Log::error('RankcrewController Image storage failed', ['lang' => $language_code, 'error' => $e->getMessage()]);
                }
            }

            try {
                // Create the blog post for this language
                $blogPost = BlogPost::create([
                    'user_id' => session('rankcrew_uid'),
                    'translation_group_id' => $translation_group_id,
                    'category_id' => $category_id,
                    'language_code' => $language_code,
                    'title' => $title,
                    'teaser' => $teaser,
                    'body' => $body,
                    'image' => $image_url,
                    'is_published' => $is_published,
                ]);

                $created_posts[$language_code] = $blogPost->id;
            } catch (\Exception $e) {
                \Log::error('RankcrewController BlogPost creation failed', [
                    'lang' => $language_code,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'nid' => array_values($created_posts)[0] ?? null,
            'translations' => $created_posts,
            'message' => 'Blog post(s) created successfully for ' . count($created_posts) . ' language(s).'
        ], 200);
    }

    private function removeFirstSentenceSimple(string $body, string $sentenceHtml): string
    {
        // Find and remove the sentence HTML from body
        $pos = mb_strpos($body, $sentenceHtml);

        if ($pos === 0) {
            // Remove from beginning
            $body = mb_substr($body, mb_strlen($sentenceHtml));
            // Trim whitespace
            $body = ltrim($body);
        }

        return $body;
    }

    // 4) /api/rankcrew/categories
    public function categories(Request $request)
    {
        if ($request->method() !== 'GET') {
            return response()->json(['error' => 'Only GET allowed on /api/rankcrew/categories.'], 405);
        }

        if (!session('rankcrew_uid')) {
            return response()->json(['error' => 'Not logged in (missing session?).'], 403);
        }

        $categories = BlogCategory::all(['id', 'name', 'slug']);

        return response()->json($categories);
    }

    /**
     * Decodes the base64 data, guesses an extension from mime_type,
     * writes the file to storage/app/public/blog_images, and returns the URL.
     */
    private function storeBase64Image($base64, $mimeType)
    {
        $decoded = base64_decode($base64);
        if (!$decoded) {
            return null;
        }

        // guess extension
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        $ext = $map[$mimeType] ?? 'jpg';

        $filename = 'rankcrew_' . uniqid() . '.' . $ext;
        // store in storage/app/public/blog_images
        $path = 'blog_images/' . $filename;

        Storage::disk('public')->put($path, $decoded);

        // Return the public URL
        return Storage::url($path);
    }
}
