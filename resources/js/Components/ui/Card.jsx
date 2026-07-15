export default function Card({
    title,
    subtitle,
    actions,
    footer,
    className = '',
    children,
}) {
    return (
        <div
            className={`app-panel ${className}`}
        >
            {(title || subtitle || actions) && (
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div>
                        {title && (
                            <h3 className="text-base font-semibold text-slate-900">
                                {title}
                            </h3>
                        )}
                        {subtitle && (
                            <p className="mt-1 text-sm text-slate-500">
                                {subtitle}
                            </p>
                        )}
                    </div>
                    {actions && <div className="flex items-center gap-2">{actions}</div>}
                </div>
            )}

            <div className="px-5 py-4">{children}</div>

            {footer && (
                <div className="border-t border-slate-200 bg-slate-50 px-5 py-3">
                    {footer}
                </div>
            )}
        </div>
    );
}
