package sdi.iot.config;

import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;
import sdi.iot.repo.ApiTokenRepository;

import java.io.IOException;

@Component
public class ExportTokenFilter extends OncePerRequestFilter {
    private final ApiTokenRepository tokens;

    @Value("${app.security.export-token-header:X-API-Token}")
    private String tokenHeader;

    public ExportTokenFilter(ApiTokenRepository tokens) {
        this.tokens = tokens;
    }

    @Override
    protected boolean shouldNotFilter(HttpServletRequest request) throws ServletException {
        String path = request.getRequestURI();
        return !(path.startsWith("/api/export/"));
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request, HttpServletResponse response, FilterChain filterChain) throws ServletException, IOException {
        String token = request.getHeader(tokenHeader);
        if (token == null || token.isBlank()) {
            token = request.getParameter("api_token");
        }
        if (token == null || token.isBlank() || tokens.findByTokenAndActiveTrue(token).isEmpty()) {
            response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
            response.setContentType("application/json");
            response.getWriter().write("{\"success\":false,\"error\":\"Invalid API token\"}");
            return;
        }
        filterChain.doFilter(request, response);
    }
}
