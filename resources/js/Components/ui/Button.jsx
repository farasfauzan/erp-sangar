import { forwardRef } from 'react';

const variants = {
    primary: 'border border-blue-700 bg-blue-700 text-white hover:bg-blue-800 focus:ring-blue-600',
    secondary: 'border border-slate-700 bg-slate-700 text-white hover:bg-slate-800 focus:ring-slate-600',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500',
    success: 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500',
    outline: 'border border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50 focus:ring-blue-600',
};

const sizes = {
    sm: 'px-3 py-1.5 text-sm',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
};

const Button = forwardRef(function Button(
    {
        variant = 'primary',
        size = 'md',
        loading = false,
        disabled = false,
        className = '',
        children,
        type = 'button',
        ...props
    },
    ref
) {
    const isDisabled = disabled || loading;

    return (
        <button
            ref={ref}
            type={type}
            disabled={isDisabled}
            aria-disabled={isDisabled}
            aria-busy={loading}
            className={`
                inline-flex items-center justify-center rounded-md font-semibold
                transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1
                ${variants[variant] || variants.primary}
                ${sizes[size] || sizes.md}
                ${isDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
                ${className}
            `}
            {...props}
        >
            {loading && (
                <svg
                    className="animate-spin -ml-1 mr-2 h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <circle
                        className="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        strokeWidth="4"
                    />
                    <path
                        className="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                    />
                </svg>
            )}
            {children}
        </button>
    );
});

export default Button;
