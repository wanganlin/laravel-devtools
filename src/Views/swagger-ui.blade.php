<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <title>Swagger UI</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui.css" />
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.32.6/index.css" />
</head>

<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-bundle.js" charset="UTF-8"> </script>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.6/swagger-ui-standalone-preset.js" charset="UTF-8"> </script>
    <script type="text/javascript">
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                urls: [
                    @foreach ($apis as $name => $url)
                        { name: '{{ $name }}', url: '{{ $url }}' },
                    @endforeach
                ],
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout"
            });
        };
    </script>
</body>

</html>