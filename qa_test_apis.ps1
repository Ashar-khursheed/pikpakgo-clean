
$baseUrl = "http://localhost:8000/api"

function Test-Endpoint {
    param($url, $method = "GET", $body = $null)
    Write-Host "Testing [$method] $url ..."
    try {
        $params = @{
            Uri = $url
            Method = $method
            ContentType = "application/json"
            ErrorAction = "Stop"
        }
        if ($body) {
            $params.Body = $body | ConvertTo-Json
        }
        $response = Invoke-WebRequest @params
        if ($response.StatusCode -eq 200) {
            Write-Host "  [OK] 200" -ForegroundColor Green
            return $response.Content | ConvertFrom-Json
        } else {
            Write-Host "  [FAIL] $($response.StatusCode)" -ForegroundColor Red
        }
    } catch {
        Write-Host "  [ERROR] $($_.Exception.Message)" -ForegroundColor Red
        if ($_.Exception.Response) {
             Write-Host "  Response: $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
             $content = [System.IO.StreamReader]($_.Exception.Response.GetResponseStream()).ReadToEnd()
             Write-Host "  Body: $content" -ForegroundColor Gray
        }
    }
    return $null
}

# 1. Health check
Test-Endpoint "$baseUrl/health"

# 2. Public Search
Test-Endpoint "$baseUrl/public/search/destinations"
Test-Endpoint "$baseUrl/public/search/popular-destinations"
Test-Endpoint "$baseUrl/public/search/autocomplete?q=mia"

# 3. Search Properties (POST)
$searchBody = @{
    checkIn = (Get-Date).AddDays(7).ToString("yyyy-MM-dd")
    checkOut = (Get-Date).AddDays(9).ToString("yyyy-MM-dd")
    location = "Miami"
    guests = 2
}
Test-Endpoint "$baseUrl/public/search/properties" "POST" $searchBody

# 4. Property details & discovery
Test-Endpoint "$baseUrl/public/properties/featured"
Test-Endpoint "$baseUrl/public/properties/new-arrivals"
Test-Endpoint "$baseUrl/public/properties/top-rated"
Test-Endpoint "$baseUrl/public/properties/amenities"
Test-Endpoint "$baseUrl/public/properties/types"
Test-Endpoint "$baseUrl/public/properties"

# 5. Blog
Test-Endpoint "$baseUrl/public/blog/posts"
Test-Endpoint "$baseUrl/public/blog/categories"
Test-Endpoint "$baseUrl/public/blog/featured"

# 6. Settings & Info
Test-Endpoint "$baseUrl/public/settings"
Test-Endpoint "$baseUrl/public/site-info"
Test-Endpoint "$baseUrl/public/faqs"

# 7. Auth (just check if routes exist)
Test-Endpoint "$baseUrl/auth/login" "POST" @{email="test@example.com"; password="password"}

# 8. Content (Header/Footer/Nav)
Test-Endpoint "$baseUrl/public/content/header"
Test-Endpoint "$baseUrl/public/content/footer"
Test-Endpoint "$baseUrl/public/content/nav"
