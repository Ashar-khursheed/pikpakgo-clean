
$baseUrl = "http://localhost:8000/api"
$endpoints = @(
    "/health",
    "/public/search/destinations",
    "/public/search/popular-destinations",
    "/public/properties/featured",
    "/public/properties/new-arrivals",
    "/public/properties/top-rated",
    "/public/properties/amenities",
    "/public/properties/types",
    "/public/blog/posts",
    "/public/settings",
    "/site-info",
    "/public/faqs"
)

foreach ($endpoint in $endpoints) {
    $url = $baseUrl + $endpoint
    Write-Host "Testing $url ..."
    try {
        $response = Invoke-WebRequest -Uri $url -Method Get -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Host "  [OK] 200" -ForegroundColor Green
        } else {
            Write-Host "  [FAIL] $($response.StatusCode)" -ForegroundColor Red
        }
    } catch {
        Write-Host "  [ERROR] $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
             Write-Host "  Response: $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
        }
    }
}
