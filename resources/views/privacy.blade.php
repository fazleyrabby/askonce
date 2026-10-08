@extends('layouts.app')
@section('title','Beta privacy notice')
@section('description', 'How AskOnce stores, protects and deletes business and client information during the beta.')
@section('main-class','form-content')
@section('content')
<h1>Beta privacy notice</h1><p class="intro">How the AskOnce application handles information.</p>
<h2>What is stored</h2><p>Business account details, business settings, client names and email addresses, request descriptions, answers, and uploaded files are stored to operate requests. Account passwords are hashed. Request link tokens are encrypted in storage and looked up using a hash.</p>
<h2>Who can access it</h2><p>Your business can access its requests and files. Anyone holding a client request link can view that request and provide answers while it is open. Treat links as private; replacing a link revokes the old one.</p>
<h2>Email and cookies</h2><p>Request and reminder emails include request details and a secure link. Business notification emails include request updates. AskOnce uses session and security cookies for sign-in and form protection. The client page does not load third-party analytics. The landing and demo pages request an anonymous visit count from a separate counting service; no cookies or personal details are sent.</p>
<h2>Keeping and deleting data</h2><p>Requests remain until the business deletes them. Deleting a request removes its answers and uploads from active storage. Backup copies, when enabled by the operator, may retain earlier data.</p>
<h2>Before a public launch</h2><p>This beta notice must be completed with the operator’s identity, privacy contact, hosting and email providers, retention periods, and the rights and request process applicable to the launch location.</p>
@endsection
