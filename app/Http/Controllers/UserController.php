<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%')
                        ->orWhere('role', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->appends($request->only('q'));

        return view('users.index', [
            'users' => $users,
            'search' => $search,
            'roles' => User::roleOptions(),
            'statuses' => User::statusOptions(),
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'managedUser' => new User([
                'role' => User::ROLE_AUDITOR,
                'status' => User::STATUS_ACTIVE,
            ]),
            'roles' => User::roleOptions(),
            'statuses' => User::statusOptions(),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        User::create($data);

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil dibuat.');
    }

    public function show(User $user)
    {
        return view('users.show', [
            'managedUser' => $user,
        ]);
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'managedUser' => $user,
            'roles' => User::roleOptions(),
            'statuses' => User::statusOptions(),
            'isSelf' => $user->is(auth()->user()),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        if ($this->isChangingOwnAccess($request->user(), $user, $data)) {
            return redirect()
                ->route('users.show', $user)
                ->with('error', 'Role dan status akun sendiri tidak boleh diubah.');
        }

        if ($this->wouldRemoveLastActiveSuperAdmin($user, $data['role'], $data['status'])) {
            return redirect()
                ->route('users.show', $user)
                ->with('error', 'Minimal harus ada satu Super Admin aktif.');
        }

        if (isset($data['password']) && $data['password'] !== '') {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('users.show', $user)
            ->with('status', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Akun sendiri tidak boleh dihapus.');
        }

        if ($this->isLastActiveSuperAdmin($user)) {
            return redirect()
                ->route('users.index')
                ->with('error', 'Minimal harus ada satu Super Admin aktif.');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'User berhasil dihapus.');
    }

    private function isChangingOwnAccess(User $currentUser, User $targetUser, array $data): bool
    {
        return $currentUser->is($targetUser)
            && ($targetUser->role !== $data['role'] || $targetUser->status !== $data['status']);
    }

    private function wouldRemoveLastActiveSuperAdmin(User $user, string $nextRole, string $nextStatus): bool
    {
        if ($user->role !== User::ROLE_SUPER_ADMIN || $user->status !== User::STATUS_ACTIVE) {
            return false;
        }

        if ($nextRole === User::ROLE_SUPER_ADMIN && $nextStatus === User::STATUS_ACTIVE) {
            return false;
        }

        return ! $this->otherActiveSuperAdminExists($user);
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        if ($user->role !== User::ROLE_SUPER_ADMIN || $user->status !== User::STATUS_ACTIVE) {
            return false;
        }

        return ! $this->otherActiveSuperAdminExists($user);
    }

    private function otherActiveSuperAdminExists(User $user): bool
    {
        return User::where('id', '<>', $user->id)
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->where('status', User::STATUS_ACTIVE)
            ->exists();
    }
}
