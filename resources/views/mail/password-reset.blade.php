<h1>Recupera tu contraseña de Garba</h1>
<p><a href="{{ route('password.reset', ['token' => $token, 'email' => $email]) }}">Elegir una contraseña nueva</a></p>
<p>El enlace es de un solo uso y vence en {{ config('auth.passwords.users.expire', 60) }} minutos. Si no lo solicitaste, puedes ignorar este correo.</p>
