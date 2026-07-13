import { useState, useEffect } from 'react';
import Modal from './Modal';
import Button from './Button';

export default function InputPromptModal({
    open = false,
    onClose,
    onSubmit,
    title = 'Input',
    message = '',
    defaultValue = '',
    placeholder = '',
    inputLabel = '',
    submitText = 'Submit',
    cancelText = 'Cancel',
}) {
    const [value, setValue] = useState(defaultValue);

    useEffect(() => {
        if (open) {
            setValue(defaultValue);
        }
    }, [open, defaultValue]);

    const handleSubmit = (e) => {
        e.preventDefault();
        onSubmit(value);
    };

    return (
        <Modal open={open} onClose={onClose} title={title} size="sm">
            <form onSubmit={handleSubmit}>
                {message && <p className="text-sm text-gray-600 mb-4">{message}</p>}
                {inputLabel && (
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        {inputLabel}
                    </label>
                )}
                <input
                    type="text"
                    value={value}
                    onChange={(e) => setValue(e.target.value)}
                    placeholder={placeholder}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    autoFocus
                />
                <div className="flex items-center justify-end gap-3 mt-6">
                    <Button
                        variant="outline"
                        onClick={(e) => {
                            e.preventDefault();
                            onClose();
                        }}
                    >
                        {cancelText}
                    </Button>
                    <Button type="submit">
                        {submitText}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
