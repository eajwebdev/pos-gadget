"use client";

import { Head, useForm, usePage } from '@inertiajs/react';
import { Eye, EyeOff, Lock, LogIn, User } from 'lucide-react';
import { useTheme } from 'next-themes';
import React, { useEffect, useState, type FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { routes } from '@/routes';

interface LoginProps {
  errors?: Record<string, string>;
  business_name?: string;
  logo_url?: string | null;
}

interface LoginFormData {
  username: string;
  password: string;
}

export default function Login({ errors: serverErrors, business_name: propBusinessName, logo_url: propLogoUrl }: LoginProps) {
  const { props } = usePage<{ app?: { name?: string; logo_url?: string | null } }>();
  const businessName = propBusinessName ?? props.app?.name ?? 'POS';
  const logo_url = propLogoUrl ?? props.app?.logo_url ?? null;
  const [showPassword, setShowPassword] = useState(false);
  const { setTheme } = useTheme();

  const { data, setData, post, processing, reset } = useForm<LoginFormData>({
    username: '',
    password: '',
  });

  useEffect(() => {
    setTheme('light');
    return () => reset('password');
  }, [setTheme, reset]);

  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    post(routes.loginPost());
  };

  return (
    <>
      <Head title="Login" />

      <div className="login-tech-shell min-h-screen flex items-center justify-center px-4 sm:px-6 lg:px-8 py-6">
        <div className="login-tech-background" aria-hidden="true">
          <div className="login-tech-grid" />
          <div className="login-tech-scan login-tech-scan-a" />
          <div className="login-tech-scan login-tech-scan-b" />
          <div className="login-tech-circuit login-tech-circuit-left">
            <span />
            <span />
            <span />
          </div>
          <div className="login-tech-circuit login-tech-circuit-right">
            <span />
            <span />
            <span />
          </div>
          <div className="login-tech-data login-tech-data-a" />
          <div className="login-tech-data login-tech-data-b" />
        </div>

        <Card className="relative z-10 w-full max-w-md shadow-2xl rounded-3xl overflow-hidden border border-white/20 bg-card/95 backdrop-blur-xl">

          {/* Header */}
          <CardHeader
            style={{ marginTop: '-24px' }}
            className="text-center pt-14 pb-12 bg-primary/5 rounded-t-3xl relative z-10 border-b border-border"
          >
            {/* Logo */}
            <div className="flex justify-center mb-1">
              <img
                src={logo_url ?? "/uploads/niz-gadgets.png"}
                alt={businessName}
                className="h-28 w-auto object-contain drop-shadow-sm"
              />
            </div>

            <CardDescription className="text-primary text-xs font-medium">
              Sign in to access your dashboard
            </CardDescription>
          </CardHeader>

          <CardContent className="px-8 pt-8 pb-10">
            <form onSubmit={submit} className="space-y-6">
              {/* Username */}
              <div className="space-y-2">
                <Label htmlFor="username" className="text-foreground font-medium">
                  Username
                </Label>
                <div className="relative">
                  <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-primary" />
                  <Input
                    id="username"
                    placeholder="Enter your username"
                    value={data.username}
                    onChange={(e) => setData('username', e.target.value)}
                    className={cn(
                      'pl-10 h-12 rounded-xl focus-visible:ring-primary',
                      serverErrors?.username && 'border-destructive focus-visible:ring-destructive'
                    )}
                    autoFocus
                    disabled={processing}
                  />
                </div>
                {serverErrors?.username && (
                  <p className="text-sm text-destructive">{serverErrors.username}</p>
                )}
              </div>

              {/* Password */}
              <div className="space-y-2">
                <Label htmlFor="password" className="text-foreground font-medium">
                  Password
                </Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-primary" />
                  <Input
                    id="password"
                    type={showPassword ? 'text' : 'password'}
                    placeholder="••••••••"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                    className={cn(
                      'pl-10 pr-12 h-12 rounded-xl focus-visible:ring-primary',
                      serverErrors?.password && 'border-destructive focus-visible:ring-destructive'
                    )}
                    disabled={processing}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 hover:bg-primary/10"
                    onClick={() => setShowPassword(!showPassword)}
                    disabled={processing}
                  >
                    {showPassword ? (
                      <EyeOff className="h-4 w-4 text-primary" />
                    ) : (
                      <Eye className="h-4 w-4 text-primary" />
                    )}
                  </Button>
                </div>
                {serverErrors?.password && (
                  <p className="text-sm text-destructive">{serverErrors.password}</p>
                )}
              </div>

              {/* Sign In Button */}
              <Button
                type="submit"
                className="w-full h-12 font-semibold text-base shadow-lg transition-all duration-200 rounded-2xl flex items-center justify-center gap-2"
                disabled={processing}
              >
                {processing ? (
                  'Signing in...'
                ) : (
                  <>
                    <LogIn className="h-5 w-5" />
                    Sign In
                  </>
                )}
              </Button>
            </form>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
