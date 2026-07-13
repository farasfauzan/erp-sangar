export default function ApplicationLogo(props) {
    return (
        <svg
            {...props}
            viewBox="0 0 200 200"
            xmlns="http://www.w3.org/2000/svg"
        >
            <defs>
                <linearGradient id="gradMain" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#1e3a5f;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#2d5a8a;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="gradAccent" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" style="stop-color:#e87d2f;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#d46b1a;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="gradLight" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#4a90d9;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#357abd;stop-opacity:1" />
                </linearGradient>
            </defs>

            <circle cx="100" cy="100" r="95" fill="url(#gradMain)" />

            <g fill="url(#gradLight)">
                <path d="M55 155 L55 80 Q55 65 70 65 L95 65 Q110 65 110 80 L110 155 Z" />
                <path d="M110 155 L110 95 Q110 80 125 80 L155 80 Q170 80 170 95 L170 155 Z" />
                <path d="M85 110 L85 95 Q85 80 95 80 L105 80 Q115 80 115 95 L115 110 Z" />
            </g>

            <g fill="#1e3a5f" opacity="0.6">
                <rect x="58" y="70" width="6" height="6" rx="1" />
                <rect x="58" y="82" width="6" height="6" rx="1" />
                <rect x="58" y="94" width="6" height="6" rx="1" />
                <rect x="58" y="106" width="6" height="6" rx="1" />
                <rect x="58" y="118" width="6" height="6" rx="1" />
            </g>

            <g fill="#1e3a5f" opacity="0.6">
                <rect x="118" y="85" width="6" height="6" rx="1" />
                <rect x="118" y="97" width="6" height="6" rx="1" />
                <rect x="118" y="109" width="6" height="6" rx="1" />
                <rect x="118" y="121" width="6" height="6" rx="1" />
            </g>

            <g fill="#1e3a5f" opacity="0.6">
                <rect x="90" y="85" width="5" height="5" rx="1" />
                <rect x="98" y="85" width="5" height="5" rx="1" />
            </g>

            <path d="M170 95 L170 70 Q178 55 188 55 L188 60 L172 60 L172 95" stroke="url(#gradAccent)" stroke-width="3" fill="none" stroke-linecap="round" />
            <circle cx="188" cy="55" r="5" fill="url(#gradAccent)" />
        </svg>
    );
}