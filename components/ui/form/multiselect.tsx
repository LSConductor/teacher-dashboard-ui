"use client";

import React from "react";
import Select from "react-select";

interface Option {
  value: string;
  label: string;
}

interface MultiSelectProps {
  options: Option[];
  value: string[];
  onChange: (selected: string[]) => void;
  placeholder?: string;
}

export function MultiSelect({
  options,
  value,
  onChange,
  placeholder = "Select options",
}: MultiSelectProps) {
  const selectedOptions = options.filter((opt) => value.includes(opt.value));

  const handleChange = (selected: any) => {
    const values = selected.map((opt: Option) => opt.value);
    onChange(values);
  };

  return (
    <Select
      isMulti
      options={options}
      value={selectedOptions}
      onChange={handleChange}
      placeholder={placeholder}
      className="text-sm font-sans"
      classNamePrefix="react-select"
    />
  );
}