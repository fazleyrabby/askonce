@extends('layouts.app')
@section('title','Beta privacy notice')
@section('description', 'How AskOnce stores, protects and deletes business and client information during the beta.')
@section('main-class','form-content')
@section('content')
<h1>Beta privacy notice</h1><p class="intro">How the AskOnce application handles information.</p>
<h2>What is stored</h2><p>Business account details, business settings, client names and email addresses, request descriptions, answers, and uploaded files are stored to operate requests. Account passwords are hashed. Request link tokens are encrypted in storage and looked up using a hash.</p>
<h2>Who can access it</h2><p>Your business can access its requests and files. Anyone holding a client request link can view that request and provide answers while it is open. Treat links as private; replacing a link revokes the old one.</p>
<h2>Email and cookies</h2><p>Request and reminder emails include request details and a secure link. Business notification emails include request updates. AskOnce uses session and security cookies for sign-in and form protection. The client page does not load third-party analytics. The landing and demo pages request an aggregate visit count from views.fazleyrabbi.xyz. Browser storage remembers the last count and whether this browser session has been counted. The counting service receives the usual connection information, including the IP address used to reach it; request answers and client-link tokens are not included.</p>
<h2>Hosting and email providers</h2><p>AskOnce runs on a self-hosted server. Cloudflare handles traffic routing and protection. Resend delivers application email, including recipient addresses, message content, and secure request links. Its <a href="https://resend.com/legal/privacy-policy" rel="noreferrer">privacy policy</a> describes its data handling.</p>
<h2>Operational logging</h2><p>Application logs record generated request identifiers, route names, response status, timing, job failures, and backup outcomes to diagnose problems. Application logs exclude passwords, email addresses, request answers, uploaded content, and secure request-link tokens. Infrastructure and email providers may keep their own connection or delivery records.</p>
<h2>Keeping and deleting data</h2><p>Requests remain until the business deletes them. Deleting a request removes its answers and uploads from active storage. Backup copies, when enabled by the operator, may retain earlier data.</p>
<h2>Before a public launch</h2><p>This beta notice must be completed with the operator’s identity, privacy contact, hosting and email providers, retention periods, and the rights and request process applicable to the launch location.</p>
@endsection
