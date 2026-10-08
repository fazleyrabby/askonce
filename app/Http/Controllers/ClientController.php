<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    public function index()
    {
        return view('clients.index', ['clients' => Client::orderBy('name')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $limit = config('app.limits.clients');
        if (Client::count() >= $limit) {
            return back()->withInput()->withErrors(['limit' => "The free beta includes up to {$limit} clients."]);
        }
        Client::create($this->validated($request));

        return redirect()->route('clients.index')->with('status', 'Client added.');
    }

    public function edit(Client $client)
    {
        Gate::authorize('update', $client);

        return view('clients.form', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        Gate::authorize('update', $client);
        $client->update($this->validated($request));

        return redirect()->route('clients.index')->with('status', 'Client updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);
    }
}
