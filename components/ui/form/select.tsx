"use client";

import * as React from "react";
import * as RadixSelect from "@radix-ui/react-select";
import { Check, ChevronDown } from "lucide-react";
import { cn } from "@/lib/utils";

export const Select = RadixSelect.Root;

export const SelectTrigger = React.forwardRef<
  React.ElementRef<typeof RadixSelect.Trigger>,
  React.ComponentPropsWithoutRef<typeof RadixSelect.Trigger>
>(({ className, children, ...props }, ref) => (
  <RadixSelect.Trigger
    ref={ref}
    className={cn(
      "flex items-center justify-between w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm ring-offset-white focus:outline-none focus:ring-2 focus:ring-blue-500",
      className
    )}
    {...props}
  >
    {children}
    <RadixSelect.Icon asChild>
      <ChevronDown className="h-4 w-4 opacity-50 ml-2" />
    </RadixSelect.Icon>
  </RadixSelect.Trigger>
));
SelectTrigger.displayName = RadixSelect.Trigger.displayName;

export const SelectValue = RadixSelect.Value;

export const SelectContent = React.forwardRef<
  React.ElementRef<typeof RadixSelect.Content>,
  React.ComponentPropsWithoutRef<typeof RadixSelect.Content>
>(({ className, children, ...props }, ref) => (
  <RadixSelect.Portal>
    <RadixSelect.Content
      ref={ref}
      className={cn(
        "z-50 min-w-[8rem] overflow-hidden rounded-md border border-gray-300 bg-white shadow-lg",
        className
      )}
      {...props}
    >
      <RadixSelect.Viewport className="p-1">{children}</RadixSelect.Viewport>
    </RadixSelect.Content>
  </RadixSelect.Portal>
));
SelectContent.displayName = RadixSelect.Content.displayName;

export const SelectItem = React.forwardRef<
  React.ElementRef<typeof RadixSelect.Item>,
  React.ComponentPropsWithoutRef<typeof RadixSelect.Item>
>(({ className, children, ...props }, ref) => (
  <RadixSelect.Item
    ref={ref}
    className={cn(
      "relative flex w-full cursor-pointer select-none items-center rounded-sm px-3 py-2 text-sm outline-none focus:bg-blue-100 focus:text-blue-900",
      className
    )}
    {...props}
  >
    <RadixSelect.ItemText>{children}</RadixSelect.ItemText>
    <RadixSelect.ItemIndicator className="absolute right-2 inline-flex items-center">
      <Check className="h-4 w-4" />
    </RadixSelect.ItemIndicator>
  </RadixSelect.Item>
));
SelectItem.displayName = RadixSelect.Item.displayName;