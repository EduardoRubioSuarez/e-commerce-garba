<h1>Activa tu acceso de instalador a Garba</h1>
<p>El administrador te invitó a consultar la tarifa de instalador. Elige tu contraseña usando este enlace, que vence en {{ config('store.access.invitation_hours', 48) }} horas.</p>
<p><a href="{{ route('installer.activate', ['token' => $token]) }}">Activar acceso</a></p>
<p>Si no esperabas esta invitación, puedes ignorarla. No compartas el enlace.</p>
