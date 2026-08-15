@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- Branded Consultfest logo (film-reel SVG, same as the favicon) instead
     of the stock Laravel notification logo. We render the SVG inline so
     the email client doesn't need to fetch an external asset (some clients
     strip remote images). --}}
<span style="display: inline-flex; align-items: center; gap: 8px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;">
<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
<path fill="#d4a853" d="M20 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm-4 2H8v14h8zm4 12h-2v2h2zM6 17H4v2h2zm14-4h-2v2h2zM6 13H4v2h2zm14-4h-2v2h2zM6 9H4v2h2zm14-4h-2v2h2zM6 5H4v2h2z"/>
</svg>
<span style="font-size: 18px; font-weight: 600; color: #d4a853; letter-spacing: -0.01em;">Consultfest</span>
</span>
</a>
</td>
</tr>