"use client";

import * as React from "react";
import { cn } from "@/lib/utils";

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Size of the button: sm, md, lg */
  size?: 'sm' | 'md' | 'lg';
  /** Variant style: default, outline, secondary */
  variant?: 'default' | 'outline' | 'secondary';
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  (
    { className, size = 'md', variant = 'default', ...props },
    ref
  ) => {
    // Size classes
    const sizeClasses =
      size === 'sm'
        ? 'px-2 py-1 text-sm'
        : size === 'lg'
        ? 'px-6 py-3 text-lg'
        : 'px-4 py-2 text-sm';

    // Variant classes
    const variantClasses =
      variant === 'outline'
        ? 'border border-gray-300 bg-white hover:bg-gray-50 text-gray-700'
        : variant === 'secondary'
        ? 'bg-gray-600 text-white hover:bg-gray-700'
        : 'border border-gray-300 bg-white hover:bg-gray-50 text-gray-700';

    return (
      <button
        ref={ref}
        className={cn(
          'inline-flex items-center rounded-md font-medium shadow-sm',
          sizeClasses,
          variantClasses,
          className
        )}
        {...props}
      />
    );
  }
);

Button.displayName = 'Button';