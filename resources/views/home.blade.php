<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | Laravel URL Parameter Demo</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 50px 60px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        .card h1 {
            font-size: 2rem;
            color: #4a3f8e;
            margin: 0 0 15px 0;
        }

        .card p {
            color: #555;
            font-size: 1rem;
            margin: 0 0 20px 0;
        }

        .example-url {
            display: inline-block;
            background: #f3f0ff;
            color: #764ba2;
            font-family: monospace;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: bold;
            border: 2px solid #d8b4fe;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Laravel URL Parameter Demo</h1>
        <p>Try visiting a URL like:</p>
        <div class="example-url">
            /welcome/{name}
        </div>
        <p style="margin-top:15px; font-size: 0.9rem; color: #999;">
            Only alphabetic characters are allowed in {name}.
        </p>
    </div>
</body>
</html>
