<?php

namespace App\Http\Controllers;

use App\Enums\RoleSlug;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $contacts = $this->getContacts($user);

        return view('chat.index', compact('contacts'));
    }

    public function conversation(User $user)
    {
        $authId = auth()->id();
        $messages = ChatMessage::where(function ($q) use ($authId, $user) {
            $q->where('sender_id', $authId)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($authId, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $authId);
        })->orderBy('created_at')->get();

        ChatMessage::where('sender_id', $user->id)
            ->where('receiver_id', $authId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $contacts = $this->getContacts(auth()->user());

        return view('chat.conversation', compact('user', 'messages', 'contacts'));
    }

    public function send(Request $request, User $user)
    {
        $data = $request->validate([
            'message' => 'nullable|string|max:5000',
            'file'    => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt|max:10240',
        ]);

        if (empty(trim($data['message'] ?? '')) && !$request->hasFile('file')) {
            return response()->json(['error' => 'Message or file required.'], 422);
        }

        $filePath = null;
        $fileType = null;
        $fileName = null;
        $fileUrl  = null;

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $filePath = $file->store('chat', 'public');
            $fileName = $file->getClientOriginalName();
            $mime     = $file->getMimeType();
            $fileType = str_starts_with($mime, 'image/') ? 'image' : 'file';
            $fileUrl  = url('storage/' . $filePath);
        }

        $msg = ChatMessage::create([
            'sender_id'   => auth()->id(),
            'receiver_id' => $user->id,
            'message'     => $data['message'] ?? '',
            'file_path'   => $filePath,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'   => true,
                'id'        => $msg->id,
                'file_path' => $fileUrl,
                'file_type' => $fileType,
                'file_name' => $fileName,
            ]);
        }

        return back();
    }

    public function poll(User $user)
    {
        $authId  = auth()->id();
        $afterId = (int) request('after_id', 0);

        $messages = ChatMessage::where(function ($q) use ($authId, $user) {
            $q->where('sender_id', $authId)->where('receiver_id', $user->id);
        })->orWhere(function ($q) use ($authId, $user) {
            $q->where('sender_id', $user->id)->where('receiver_id', $authId);
        })
        ->where('id', '>', $afterId)
        ->orderBy('id')
        ->get()
        ->map(function ($m) {
            $fileUrl  = null;
            $fileType = null;
            $fileName = null;

            if ($m->file_path) {
                $fileUrl  = url('storage/' . $m->file_path);
                $ext      = strtolower(pathinfo($m->file_path, PATHINFO_EXTENSION));
                $fileType = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'image' : 'file';
                $fileName = basename($m->file_path);
            }

            return [
                'id'         => $m->id,
                'sender_id'  => $m->sender_id,
                'message'    => $m->message,
                'file_url'   => $fileUrl,
                'file_type'  => $fileType,
                'file_name'  => $fileName,
                'created_at' => $m->created_at,
            ];
        });

        return response()->json(['messages' => $messages]);
    }

    private function getContacts(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $query = User::with('role')->where('id', '!=', $user->id)->whereNotNull('role_id');

        if ($user->isAdmin()) {
            // Admin can chat with everyone
            return $query->get();
        }

        if ($user->isTeacher()) {
            // Teacher can chat with admins and students
            return $query->whereHas('role', fn ($q) =>
                $q->whereIn('slug', [RoleSlug::ADMIN, RoleSlug::STUDENT])
            )->get();
        }

        // Student can chat with teachers and admins
        return $query->whereHas('role', fn ($q) =>
            $q->whereIn('slug', [RoleSlug::ADMIN, RoleSlug::TEACHER])
        )->get();
    }
}
