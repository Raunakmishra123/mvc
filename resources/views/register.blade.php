<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        input, button { display: block; margin: 10px 0; padding: 6px; }
    </style>
</head>
<body>
    <h2>Register</h2>
    <form action="/register" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="text" name="name" placeholder="Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="file" name="resume" accept=".pdf">
        <button type="submit">Register</button>
    </form>
</body>
</html>
