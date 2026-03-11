<!DOCTYPE html>
<html>
<head>
    <title>Test Bootstrap</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>✅ Test Bootstrap</h4>
            <p>Si tu vois ce message avec un fond vert, Bootstrap fonctionne !</p>
        </div>
        
        <button class="btn btn-primary">Bouton Bootstrap</button>
        
        <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script>
            console.log('=== TEST BOOTSTRAP ===');
            console.log('typeof bootstrap:', typeof bootstrap);
            console.log('bootstrap version:', bootstrap?.Tooltip?.VERSION || 'non disponible');
        </script>
    </div>
</body>
</html>